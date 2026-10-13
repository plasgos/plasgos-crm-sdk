<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Http;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Plasgos\CrmSdk\Exception\ChannelException;
use Plasgos\CrmSdk\Exception\ExceptionFactory;
use Plasgos\CrmSdk\Exception\PlasgosApiException;
use Plasgos\CrmSdk\Exception\RateLimitException;
use Plasgos\CrmSdk\Exception\ServerException;
use Plasgos\CrmSdk\Exception\ServiceUnavailableException;
use Plasgos\CrmSdk\Exception\TransportException;
use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Signature;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP layer for API v3 (`/api/v3/*`): signing, idempotency, retry policy, envelope parsing.
 * See docs/SDK_SPEC.md §2, §4–§6.
 */
final class ApiClient
{
    public const PREFIX = '/api/v3';

    /** Free-form maps in v3 request bodies (OpenAPI `additionalProperties`). */
    private const OBJECT_MAPS = ['custom_fields'];

    private const RETRY_BASE_MS = 500;
    private const RETRY_CAP_MS = 8000;

    /**
     * @param \Closure(float): void $sleeper
     * @param \Closure(): int $clock
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly int $maxRetries,
        private readonly int $maxRetryWait,
        private readonly \Closure $sleeper,
        private readonly \Closure $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|\JsonSerializable|\stdClass|null $body
     * @return Response<mixed, array<string, mixed>>
     */
    public function request(string $method, string $path, array $query = [], array|\JsonSerializable|\stdClass|null $body = null, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        $options ??= new RequestOptions();
        $maxRetries = max(0, $options->maxRetries ?? $this->maxRetries);
        $method = strtoupper($method);
        $url = rtrim($this->baseUrl, '/') . self::PREFIX . '/' . ltrim($path, '/');
        $queryString = self::buildQuery($query);
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }
        $requestTarget = self::requestTarget($url);
        $rawBody = $body === null ? '' : json_encode(self::preserveObjectMaps($body), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        // One key per intent: generated once here and reused by every retry.
        if ($idempotencyKey === null && $method === 'POST') {
            $idempotencyKey = self::uuid4();
        }

        $logPath = explode('?', $requestTarget, 2)[0];

        for ($attempt = 0; ; $attempt++) {
            $started = hrtime(true);
            try {
                $response = $this->attempt($method, $url, $requestTarget, $rawBody, $idempotencyKey, $options);
                $this->logAttempt($method, $logPath, $attempt, $started, $response->httpStatus, $response->requestId, null, null);
                return $response;
            } catch (PlasgosApiException $e) {
                $wait = $this->retryWait($e, $method, $attempt, $maxRetries);
                $this->logAttempt($method, $logPath, $attempt, $started, $e->httpStatus ?: null, $e->requestId, $e->errorCode, $wait);
                if ($wait === null) {
                    throw $e;
                }
                ($this->sleeper)($wait);
            }
        }
    }

    /**
     * @param array<string, scalar|null> $query
     * @return Page<mixed>
     */
    public function requestPage(string $path, array $query = [], ?RequestOptions $options = null): Page
    {
        return new Page(
            $this->request('GET', $path, $query, options: $options),
            fn (int $page): Page => $this->requestPage($path, ['page' => $page] + $query, $options),
        );
    }

    /** @return Response<mixed, array<string, mixed>> */
    private function attempt(string $method, string $url, string $requestTarget, string $rawBody, ?string $idempotencyKey, RequestOptions $options): Response
    {
        $timestamp = ($this->clock)();
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($options->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $request = $request
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);
        foreach (Signature::headers($this->apiKey, $this->secretKey, $method, $requestTarget, $rawBody, $timestamp) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }
        if ($rawBody !== '') {
            $request = $request
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream($rawBody));
        }

        try {
            $response = $this->send($request, $options->timeout);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException("Network error requesting {$url}: {$e->getMessage()}", previous: $e, errorCode: 'transport_error');
        }

        return self::parse($response);
    }

    /**
     * PSR-18 has no per-request timeout; Guzzle takes it as a request option. The other options mirror
     * what Guzzle's own sendRequest() forces, so an injected Guzzle client behaves the same.
     */
    private function send(RequestInterface $request, ?float $timeout): ResponseInterface
    {
        if ($timeout === null) {
            return $this->client->sendRequest($request);
        }
        if (! $this->client instanceof GuzzleClientInterface) {
            throw new \LogicException('RequestOptions timeout needs the Guzzle transport; set the timeout on your own PSR-18 client instead');
        }
        return $this->client->send($request, ['timeout' => $timeout, 'connect_timeout' => $timeout, 'http_errors' => false, 'allow_redirects' => false]);
    }

    /** @return Response<mixed, array<string, mixed>> */
    public static function parse(ResponseInterface $response): Response
    {
        $status = $response->getStatusCode();
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }
        $requestId = $headers['x-request-id'] ?? null;
        $text = (string) $response->getBody();
        $body = null;
        if ($text !== '') {
            try {
                $body = Json::object(json_decode($text, true, 512, JSON_THROW_ON_ERROR));
            } catch (\JsonException) {
                $body = null;
            }
        }

        if ($status < 200 || $status >= 300) {
            $retryAfter = isset($headers['retry-after']) && ctype_digit($headers['retry-after']) ? (int) $headers['retry-after'] : null;
            throw ExceptionFactory::fromV3($status, $body, $requestId, $retryAfter);
        }

        if ($text !== '' && ($body === null || ! array_key_exists('data', $body))) {
            throw new PlasgosApiException('Plasgos CRM response is not an API v3 envelope', $status, null, [], $text, $requestId, errorCode: 'unexpected_response', errorType: 'api_error');
        }

        $meta = Json::object($body['meta'] ?? null) ?? [];
        $int = static fn (?string $v): ?int => $v === null || $v === '' ? null : (int) $v;

        return new Response(
            httpStatus: $status,
            message: Json::string($body['message'] ?? null),
            data: $body['data'] ?? null,
            meta: $meta,
            requestId: Json::stringOrNull($meta['request_id'] ?? null) ?? $requestId,
            rateLimit: [
                'limit' => $int($headers['ratelimit-limit'] ?? null),
                'remaining' => $int($headers['ratelimit-remaining'] ?? null),
                'reset' => $int($headers['ratelimit-reset'] ?? null),
            ],
            replayed: strtolower($headers['idempotent-replayed'] ?? '') === 'true',
            headers: $headers,
        );
    }

    /**
     * One debug record per attempt (docs/SDK_SPEC.md §1.2): method, path without the query, status,
     * duration, request id, error code and retry wait — never headers or bodies, so keys, signatures,
     * phone numbers and message text cannot leak.
     */
    private function logAttempt(string $method, string $path, int $attempt, int|float $started, ?int $status, ?string $requestId, ?string $errorCode, ?float $retryIn): void
    {
        if ($this->logger === null) {
            return;
        }
        $context = [
            'method' => $method,
            'path' => $path,
            'attempt' => $attempt + 1,
            'status' => $status,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1e6),
            'request_id' => $requestId,
            'error_code' => $errorCode,
            'retry_in_ms' => $retryIn === null ? null : (int) round($retryIn * 1000),
        ];
        $outcome = $status ?? $errorCode;
        $retry = $retryIn === null ? '' : ", retrying in {$context['retry_in_ms']} ms";
        try {
            $this->logger->debug("Plasgos CRM {$method} {$path} attempt {$context['attempt']} -> {$outcome} in {$context['duration_ms']} ms{$retry}", $context);
        } catch (\Throwable) {
            // A broken logger must not break the call.
        }
    }

    /**
     * Seconds to wait before retrying, or null when this error is not retried (§6).
     */
    private function retryWait(PlasgosApiException $e, string $method, int $attempt, int $maxRetries): ?float
    {
        if ($attempt >= $maxRetries) {
            return null;
        }
        if ($e instanceof RateLimitException) {
            // Daily quota does not recover in seconds — hand it back to the caller.
            if ($e->errorCode !== 'rate_limited') {
                return null;
            }
            if ($e->retryAfter !== null) {
                return $e->retryAfter <= $this->maxRetryWait ? (float) $e->retryAfter : null;
            }
            return $this->backoff($attempt);
        }
        // The first request with this Idempotency-Key is still running (e.g. it timed out client-side);
        // retrying with the same key returns its stored answer once it finishes.
        if ($e->errorCode === 'idempotency_in_progress') {
            return $this->backoff($attempt);
        }
        $transient = $e instanceof TransportException || $e instanceof ChannelException || $e instanceof ServiceUnavailableException;
        // A plain 500 may have partially executed; only reads are retried on it.
        if ($transient || ($method === 'GET' && $e instanceof ServerException)) {
            return $this->backoff($attempt);
        }
        return null;
    }

    private function backoff(int $attempt): float
    {
        $cap = min(self::RETRY_CAP_MS, self::RETRY_BASE_MS * (2 ** $attempt));
        return random_int(0, $cap) / 1000; // full jitter
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private static function buildQuery(array $query): string
    {
        $clean = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        return http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed>|\JsonSerializable|\stdClass $body
     * @return array<string, mixed>|\JsonSerializable|\stdClass
     *
     * PHP decodes an empty JSON object as `[]`, which would be re-sent as a JSON array. Free-form maps
     * in v3 request bodies (`custom_fields`) are always objects, so an empty one is sent as `{}` —
     * `update($id, ['custom_fields' => $contact->data['custom_fields']])` round-trips.
     */
    private static function preserveObjectMaps(array|\JsonSerializable|\stdClass $body): array|\JsonSerializable|\stdClass
    {
        if (is_array($body)) {
            foreach (self::OBJECT_MAPS as $key) {
                if (array_key_exists($key, $body) && $body[$key] === []) {
                    $body[$key] = new \stdClass();
                }
            }
        }
        return $body;
    }

    private static function requestTarget(string $url): string
    {
        $parts = parse_url($url);
        $target = $parts['path'] ?? '/';
        if (($parts['query'] ?? '') !== '') {
            $target .= '?' . $parts['query'];
        }
        return $target;
    }

    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
