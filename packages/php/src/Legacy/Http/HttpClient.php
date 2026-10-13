<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Http;

use Plasgos\CrmSdk\Exception\ExceptionFactory;
use Plasgos\CrmSdk\Exception\PlasgosApiException;
use Plasgos\CrmSdk\Exception\RateLimitException;
use Plasgos\CrmSdk\Exception\ServerException;
use Plasgos\CrmSdk\Exception\TransportException;
use Plasgos\CrmSdk\Http\Json;
use Plasgos\CrmSdk\Legacy\Envelope;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpClient
{
    private const RETRY_BASE_MS = 500;
    private const RETRY_CAP_MS = 8000;

    /**
     * @param callable(): array<string, string> $authHeaders  fresh auth headers per attempt
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly int $maxRetries,
        private $authHeaders,
    ) {}

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null  $body
     * @return Envelope<mixed>
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
    ): Envelope {
        $url = $this->buildUrl($path, $query);
        $isIdempotent = strtoupper($method) === 'GET';
        $maxAttempts = $isIdempotent ? $this->maxRetries + 1 : 1;

        $lastError = null;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                return $this->attempt($method, $url, $body);
            } catch (PlasgosApiException $e) {
                $lastError = $e;
                $retryable = $isIdempotent
                    && $attempt < $maxAttempts - 1
                    && ($e instanceof TransportException
                        || $e instanceof ServerException
                        || $e instanceof RateLimitException);
                if (! $retryable) {
                    throw $e;
                }
                usleep($this->backoffMicros($attempt));
            }
        }
        throw $lastError ?? new PlasgosApiException('Request failed');
    }

    /**
     * @param array<string, mixed>|null $body
     * @return Envelope<mixed>
     */
    private function attempt(string $method, string $url, ?array $body): Envelope
    {
        $request = $this->requestFactory->createRequest(strtoupper($method), $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        foreach (($this->authHeaders)() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                "Network error requesting {$url}: {$e->getMessage()}",
                0,
                null,
                [],
                null,
                null,
                $e,
            );
        }

        $requestId = $response->getHeaderLine('x-request-id') ?: ($response->getHeaderLine('x-correlation-id') ?: null);

        return self::normalize(
            $response->getStatusCode(),
            (string) $response->getBody(),
            $requestId,
        );
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $filtered = array_filter($query, static fn ($v) => $v !== null);
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }
        return $url;
    }

    private function backoffMicros(int $attempt): int
    {
        $exp = min(self::RETRY_CAP_MS, self::RETRY_BASE_MS * (2 ** $attempt));
        return random_int(0, $exp) * 1000; // full jitter, ms -> µs
    }

    /**
     * Normalise the many server envelope shapes into one Envelope, or throw.
     * See docs/SDK_SPEC.md §4/§5.
     * @return Envelope<mixed>
     */
    public static function normalize(int $httpStatus, string $bodyText, ?string $requestId): Envelope
    {
        $body = null;
        if ($bodyText !== '') {
            try {
                $body = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $body = $bodyText;
            }
        }

        $obj = is_array($body) && ! array_is_list($body) ? Json::object($body) : null;
        $ok = $httpStatus >= 200 && $httpStatus < 300;

        // { success, data } shape
        if ($obj !== null && array_key_exists('success', $obj) && ! array_key_exists('status', $obj)) {
            if ($obj['success'] === true && $ok) {
                return new Envelope(
                    status: $httpStatus,
                    message: isset($obj['message']) && is_string($obj['message']) ? $obj['message'] : null,
                    data: $obj['data'] ?? null,
                    metadata: [],
                    raw: $body,
                    httpStatus: $httpStatus,
                );
            }
            throw ExceptionFactory::fromResponse(
                $httpStatus,
                null,
                is_string($obj['message'] ?? null) ? $obj['message'] : "Request failed with status {$httpStatus}",
                self::extractIssues($obj),
                $body,
                $requestId,
            );
        }

        $apiStatus = $obj !== null && array_key_exists('status', $obj) ? $obj['status'] : null;
        $message = $obj !== null && is_string($obj['message'] ?? null) ? $obj['message'] : null;
        $apiErrorStatus = is_int($apiStatus) && $apiStatus >= 400 ? $apiStatus : null;

        if (! $ok || $apiErrorStatus !== null) {
            throw ExceptionFactory::fromResponse(
                ($ok ? $apiErrorStatus : null) ?? $httpStatus,
                is_int($apiStatus) || is_string($apiStatus) ? $apiStatus : null,
                $message ?? "Request failed with status {$httpStatus}",
                $obj !== null ? self::extractIssues($obj) : [],
                $body,
                $requestId,
            );
        }

        if ($obj === null) {
            return new Envelope($httpStatus, null, $body, [], $body, $httpStatus);
        }

        $metadata = Json::object($obj['metadata'] ?? null) ?? Json::object($obj['meta'] ?? null) ?? [];

        return new Envelope(
            status: is_int($apiStatus) || is_bool($apiStatus) ? $apiStatus : $httpStatus,
            message: $message,
            data: array_key_exists('data', $obj) ? $obj['data'] : $obj,
            metadata: $metadata,
            raw: $body,
            httpStatus: $httpStatus,
        );
    }

    /**
     * @param array<string, mixed> $obj
     * @return list<array{message: string, path?: array<int, string|int>}>
     */
    private static function extractIssues(array $obj): array
    {
        $raw = $obj['errors'] ?? null;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $e) {
            if (is_array($e)) {
                $issue = ['message' => Json::string($e['message'] ?? null)];
                if (isset($e['path']) && is_array($e['path'])) {
                    $issue['path'] = array_values(array_filter($e['path'], static fn (mixed $p): bool => is_string($p) || is_int($p)));
                }
                $out[] = $issue;
            } else {
                $out[] = ['message' => Json::string($e)];
            }
        }
        return $out;
    }
}
