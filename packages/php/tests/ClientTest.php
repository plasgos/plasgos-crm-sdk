<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Content;
use Plasgos\CrmSdk\Enum\BaseUrl;
use Plasgos\CrmSdk\Exception\AuthenticationException;
use Plasgos\CrmSdk\Exception\BadRequestException;
use Plasgos\CrmSdk\Exception\ChannelException;
use Plasgos\CrmSdk\Exception\ConflictException;
use Plasgos\CrmSdk\Exception\NotFoundException;
use Plasgos\CrmSdk\Exception\PermissionException;
use Plasgos\CrmSdk\Exception\PlanException;
use Plasgos\CrmSdk\Exception\PlasgosApiException;
use Plasgos\CrmSdk\Exception\RateLimitException;
use Plasgos\CrmSdk\Exception\ServerException;
use Plasgos\CrmSdk\Exception\ServiceUnavailableException;
use Plasgos\CrmSdk\Exception\SignatureExpiredException;
use Plasgos\CrmSdk\Exception\TransportException;
use Plasgos\CrmSdk\Exception\ValidationException;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Signature;
use Plasgos\CrmSdk\Tests\Support\RecordingClient;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;

final class ClientTest extends TestCase
{
    private const NOW = 1791530100;

    /** @var list<float> */
    private array $sleeps = [];

    private function client(RecordingClient $http, int $maxRetries = 2): Client
    {
        return new Client('ak_live_123', 'sk_live_abc', new ClientConfig(
            baseUrl: BaseUrl::Sandbox,
            maxRetries: $maxRetries,
            httpClient: $http,
            sleeper: function (float $s): void { $this->sleeps[] = $s; },
            clock: static fn (): int => self::NOW,
        ));
    }

    /** @param mixed $data */
    private static function ok(int $status, mixed $data, array $meta = [], string $message = 'OK', array $headers = []): \GuzzleHttp\Psr7\Response
    {
        return RecordingClient::json($status, ['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => 'req_ok'] + $meta], ['X-Request-Id' => 'req_ok'] + $headers);
    }

    private static function error(int $status, string $code, string $type, array $headers = [], ?array $details = null, array $fields = []): \GuzzleHttp\Psr7\Response
    {
        return RecordingClient::json($status, [
            'success' => false,
            'message' => 'Pesan galat',
            'error' => ['code' => $code, 'type' => $type, 'details' => $details, 'fields' => $fields, 'doc_url' => "https://docs.plasgos.co.id/api/v3/errors#{$code}"],
            'meta' => ['request_id' => 'req_err'],
        ], $headers);
    }

    private static function networkError(): \Closure
    {
        return static function (RequestInterface $request): never {
            throw new class ('timeout', $request) extends \RuntimeException implements NetworkExceptionInterface {
                public function __construct(string $m, private RequestInterface $r) { parent::__construct($m); }
                public function getRequest(): RequestInterface { return $this->r; }
            };
        };
    }

    public function testSignsMethodPathAndExactBody(): void
    {
        $http = new RecordingClient([self::ok(202, ['id' => 'msg_1', 'status' => 'pending'])]);
        $this->client($http)->messages->send([
            'endpoint_id' => '6281100000001',
            'contact' => ['phone_number' => '081234567890'],
            'content' => Content::template('tpl_1')->dynamic('name', '{{full_name}}'),
        ], idempotencyKey: 'wc-1-processing');

        $req = $http->lastRequest();
        $body = (string) $req->getBody();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('https://api-crm.sandbox.plasgos.co.id/api/v3/messages', (string) $req->getUri());
        self::assertSame('ak_live_123', $req->getHeaderLine('Plasgos-Api-Key'));
        self::assertSame((string) self::NOW, $req->getHeaderLine('Plasgos-Timestamp'));
        self::assertSame(Signature::sign('sk_live_abc', self::NOW, 'POST', '/api/v3/messages', $body), $req->getHeaderLine('Plasgos-Signature'));
        self::assertSame('wc-1-processing', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame('application/json; charset=utf-8', $req->getHeaderLine('Content-Type'));
        self::assertSame(
            ['type' => 'template', 'template' => ['template_id' => 'tpl_1', 'variables' => [['param' => 'name', 'type' => 'dynamic', 'value' => '{{full_name}}']]]],
            json_decode($body, true)['content'],
        );
        self::assertStringContainsString('{{full_name}}', $body);
        self::assertMatchesRegularExpression('#^plasgos-crm-sdk-php/2\.0\.0 php/#', $req->getHeaderLine('User-Agent'));
    }

    public function testQueryIsSignedAsSentAndNullsDropped(): void
    {
        $http = new RecordingClient([self::ok(200, [], ['pagination' => ['page' => 2, 'per_page' => 50, 'total' => 0, 'total_pages' => 0, 'has_next_page' => false, 'has_prev_page' => true]])]);
        $this->client($http)->contacts->list(['search' => 'budi santoso', 'page' => 2, 'per_page' => 50, 'tag' => null]);

        $req = $http->lastRequest();
        self::assertSame('https://api-crm.sandbox.plasgos.co.id/api/v3/contacts?search=budi%20santoso&page=2&per_page=50', (string) $req->getUri());
        self::assertSame('', (string) $req->getBody());
        self::assertFalse($req->hasHeader('Idempotency-Key'), 'GET carries no idempotency key');
        self::assertSame(Signature::sign('sk_live_abc', self::NOW, 'GET', '/api/v3/contacts?search=budi%20santoso&page=2&per_page=50', ''), $req->getHeaderLine('Plasgos-Signature'));
    }

    public function testPathSegmentsAreEncoded(): void
    {
        $http = new RecordingClient([self::ok(200, ['id' => 'x'])]);
        $this->client($http)->contacts->get('../webhooks');
        self::assertSame('/api/v3/contacts/..%2Fwebhooks', $http->lastRequest()->getUri()->getPath());
    }

    public function testPostGetsOneGeneratedKeyReusedAcrossRetries(): void
    {
        $http = new RecordingClient([
            self::error(503, 'channel_unavailable', 'service_unavailable'),
            self::networkError(),
            self::ok(201, ['id' => 'c1'], ['created' => true]),
        ]);
        $response = $this->client($http)->contacts->upsert(['phone_number' => '0812']);

        self::assertSame(201, $response->httpStatus);
        self::assertTrue($response->meta['created']);
        $keys = array_map(static fn (RequestInterface $r): string => $r->getHeaderLine('Idempotency-Key'), $http->requests);
        self::assertCount(3, $keys);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $keys[0]);
        self::assertSame([$keys[0], $keys[0], $keys[0]], $keys);
        self::assertCount(2, $this->sleeps);
    }

    public function testRateLimitedHonoursRetryAfter(): void
    {
        $http = new RecordingClient([self::error(429, 'rate_limited', 'rate_limit_error', ['Retry-After' => '7']), self::ok(200, ['tenant' => ['id' => '1']])]);
        $this->client($http)->me->get();
        self::assertSame([7.0], $this->sleeps);
    }

    public function testIdempotencyInProgressIsRetriedWithTheSameKey(): void
    {
        $http = new RecordingClient([self::error(409, 'idempotency_in_progress', 'invalid_request_error'), self::ok(202, ['id' => 'm1'])]);
        $this->client($http)->messages->send(['endpoint_id' => 'e', 'contact' => ['id' => 'c'], 'content' => ['type' => 'text', 'text' => ['body' => 'x']]], 'order-1');
        self::assertCount(2, $http->requests);
        self::assertSame(['order-1', 'order-1'], array_map(static fn ($r) => $r->getHeaderLine('Idempotency-Key'), $http->requests));
        self::assertCount(1, $this->sleeps);

        $http = new RecordingClient([self::error(409, 'identity_taken', 'invalid_request_error', [], ['contact_id' => 'c1'])]);
        try {
            $this->client($http)->contacts->create(['phone_number' => '0812']);
            self::fail('identity_taken harus dilempar');
        } catch (ConflictException $e) {
            self::assertSame('identity_taken', $e->errorCode);
        }
        self::assertCount(1, $http->requests);
    }

    public function testEmptyCustomFieldsAreSentAsAnObject(): void
    {
        $http = new RecordingClient([self::ok(200, ['id' => 'c1'])]);
        // PHP membaca {} dari server sebagai []; dikirim balik harus tetap objek (server: z.record).
        $this->client($http)->contacts->update('c1', ['name' => 'Budi', 'custom_fields' => []]);
        self::assertSame('{"name":"Budi","custom_fields":{}}', (string) $http->lastRequest()->getBody());
    }

    public function testLongRetryAfterAndDailyQuotaAreHandedBack(): void
    {
        $http = new RecordingClient([self::error(429, 'daily_quota_exceeded', 'rate_limit_error', ['Retry-After' => '3600'], ['used' => 1000, 'limit' => 1000])]);
        try {
            $this->client($http)->me->get();
            self::fail('expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame('daily_quota_exceeded', $e->errorCode);
            self::assertSame(3600, $e->retryAfter);
            self::assertSame(['used' => 1000, 'limit' => 1000], $e->details);
            self::assertSame([], $this->sleeps);
        }

        $http = new RecordingClient([self::error(429, 'rate_limited', 'rate_limit_error', ['Retry-After' => '45'])]);
        $this->expectException(RateLimitException::class);
        $this->client($http)->me->get();
    }

    public function testPlainServerErrorRetriesReadsButNotWrites(): void
    {
        $http = new RecordingClient([self::error(500, 'internal_error', 'api_error'), self::ok(200, [])]);
        $this->client($http)->channels->list();
        self::assertCount(2, $http->requests);

        $http = new RecordingClient([self::error(500, 'internal_error', 'api_error')]);
        try {
            $this->client($http)->leads->create(['contact' => ['id' => 'c'], 'pipeline_id' => 'p']);
            self::fail('expected ServerException');
        } catch (ServerException $e) {
            self::assertCount(1, $http->requests, 'a 500 POST may have partially run — not retried');
        }
    }

    public function testClientErrorsAreNeverRetried(): void
    {
        $http = new RecordingClient([self::error(422, 'requires_template', 'invalid_request')]);
        try {
            $this->client($http)->messages->send(['endpoint_id' => 'e', 'contact' => ['id' => 'c'], 'content' => Content::text('x')]);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('requires_template', $e->errorCode);
            self::assertCount(1, $http->requests);
        }
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $http = new RecordingClient([self::networkError(), self::networkError()]);
        $this->expectException(TransportException::class);
        $this->client($http, 1)->me->get();
    }

    public function testRequestOptionsApplyToThisCallOnly(): void
    {
        $http = new RecordingClient([self::networkError(), self::ok(200, []), self::ok(200, [])]);
        $client = $this->client($http, maxRetries: 2);
        try {
            $client->contacts->upsert(['phone_number' => '0812'], 'wc-1', new RequestOptions(maxRetries: 0, headers: ['X-Trace' => 't1']));
            self::fail('expected TransportException');
        } catch (TransportException) {
        }
        self::assertCount(1, $http->requests);
        self::assertSame('wc-1', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('t1', $http->requests[0]->getHeaderLine('X-Trace'));

        $client->contacts->list(['per_page' => 1], new RequestOptions(headers: ['X-Trace' => 't2']));
        $client->me->get();
        self::assertSame('t2', $http->requests[1]->getHeaderLine('X-Trace'));
        self::assertFalse($http->requests[2]->hasHeader('X-Trace'));
    }

    public function testRequestOptionsRejectHeadersOwnedByTheSdk(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Plasgos-Signature" is set by the SDK');
        new RequestOptions(headers: ['Plasgos-Signature' => 'v1=forged']);
    }

    public function testTimeoutPerCallGoesToGuzzle(): void
    {
        $seen = [];
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use (&$seen) {
                $seen[] = $options['timeout'] ?? null;
                return self::ok(200, ['id' => 'c1']);
            },
            function (RequestInterface $request, array $options) use (&$seen) {
                $seen[] = $options['timeout'] ?? null;
                return self::error(404, 'contact_not_found', 'not_found_error');
            },
        ]);
        $client = new Client('ak_live_123', 'sk_live_abc', new ClientConfig(
            baseUrl: BaseUrl::Sandbox,
            timeout: 30,
            httpClient: new GuzzleClient(['handler' => HandlerStack::create($mock)]),
            clock: static fn (): int => self::NOW,
        ));
        $client->contacts->get('c1', new RequestOptions(timeout: 2.5));
        try {
            // Guzzle's http_errors defaults to true; the SDK turns it off like sendRequest() does.
            $client->contacts->get('c2', new RequestOptions(timeout: 1));
            self::fail('expected NotFoundException');
        } catch (NotFoundException) {
        }
        self::assertSame([2.5, 1.0], $seen);
    }

    public function testEveryResourceMethodTakesRequestOptionsLast(): void
    {
        $count = 0;
        foreach (glob(__DIR__ . '/../src/Resource/*.php') as $file) {
            $class = 'Plasgos\\CrmSdk\\Resource\\' . basename($file, '.php');
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $params = $method->getParameters();
                $last = end($params);
                self::assertNotFalse($last, "{$class}::{$method->getName()}");
                self::assertSame('options', $last->getName(), "{$class}::{$method->getName()}");
                self::assertSame('?' . RequestOptions::class, (string) $last->getType(), "{$class}::{$method->getName()}");
                $count++;
            }
        }
        self::assertSame(35, $count);
    }

    public function testTimeoutPerCallNeedsGuzzle(): void
    {
        $this->expectException(\LogicException::class);
        $this->client(new RecordingClient([]))->me->get(new RequestOptions(timeout: 1));
    }

    public function testLoggerGetsOneDebugRecordPerAttemptWithoutSecrets(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
        $http = new RecordingClient([self::error(503, 'service_unavailable', 'service_unavailable'), self::ok(200, [])]);
        $client = new Client('ak_live_123', 'sk_live_abc', new ClientConfig(
            httpClient: $http,
            logger: $logger,
            sleeper: static function (float $s): void {},
            clock: static fn (): int => self::NOW,
        ));
        $client->contacts->list(['search' => '0812345']);

        self::assertCount(2, $logger->records);
        [$level, $message, $first] = $logger->records[0];
        self::assertSame('debug', $level);
        self::assertSame(['GET', '/api/v3/contacts', 1, 503, 'service_unavailable'], [$first['method'], $first['path'], $first['attempt'], $first['status'], $first['error_code']]);
        self::assertIsInt($first['retry_in_ms']);
        self::assertSame([2, 200, 'req_ok', null], [$logger->records[1][2]['attempt'], $logger->records[1][2]['status'], $logger->records[1][2]['request_id'], $logger->records[1][2]['retry_in_ms']]);
        $text = json_encode($logger->records, JSON_THROW_ON_ERROR);
        foreach (['ak_live_123', 'sk_live_abc', '0812345', 'v1='] as $secret) {
            self::assertStringNotContainsString($secret, $text);
        }
    }

    /** @return iterable<string, array{int, string, string, class-string<PlasgosApiException>}> */
    public static function errorMapping(): iterable
    {
        yield 'malformed json' => [400, 'malformed_json', 'invalid_request', BadRequestException::class];
        yield 'bad signature' => [401, 'signature_invalid', 'authentication_error', AuthenticationException::class];
        yield 'expired' => [401, 'signature_expired', 'authentication_error', SignatureExpiredException::class];
        yield 'scope' => [403, 'scope_missing', 'permission_error', PermissionException::class];
        yield 'plan' => [403, 'not_entitled', 'permission_error', PlanException::class];
        yield 'not found' => [404, 'contact_not_found', 'not_found', NotFoundException::class];
        yield 'conflict' => [409, 'identity_taken', 'conflict', ConflictException::class];
        yield 'validation' => [422, 'validation_failed', 'invalid_request', ValidationException::class];
        yield 'channel' => [502, 'channel_provider_error', 'channel_error', ChannelException::class];
        yield 'unavailable' => [503, 'channel_unavailable', 'service_unavailable', ServiceUnavailableException::class];
        yield 'server' => [500, 'internal_error', 'api_error', ServerException::class];
    }

    /**
     * @dataProvider errorMapping
     * @param class-string<PlasgosApiException> $class
     */
    public function testMapsV3ErrorEnvelope(int $status, string $code, string $type, string $class): void
    {
        // A 400 WITH per-field errors is a ValidationException (same rule as legacy); without, a BadRequestException.
        $fields = $status === 400 ? [] : [['field' => 'contact.phone_number', 'code' => 'required', 'message' => 'wajib']];
        $http = new RecordingClient([self::error($status, $code, $type, [], ['contact_id' => 'c9'], $fields)]);
        try {
            $this->client($http, 0)->contacts->get('c1');
            self::fail("expected {$class}");
        } catch (PlasgosApiException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($status, $e->httpStatus);
            self::assertSame($code, $e->errorCode);
            self::assertSame($type, $e->errorType);
            self::assertSame('Pesan galat', $e->getMessage());
            self::assertSame(['contact_id' => 'c9'], $e->details);
            if ($fields !== []) {
                self::assertSame('contact.phone_number', $e->fields[0]['field']);
                self::assertSame(['contact', 'phone_number'], $e->errors[0]['path'], 'legacy ->errors view kept');
            }
            self::assertSame('req_err', $e->requestId);
            self::assertStringEndsWith("#{$code}", (string) $e->docUrl);
        }
    }

    public function testSignatureExpiredIsAlsoAnAuthenticationError(): void
    {
        $http = new RecordingClient([self::error(401, 'signature_expired', 'authentication_error')]);
        $this->expectException(AuthenticationException::class);
        $this->client($http, 0)->me->get();
    }

    public function testNonEnvelopeResponses(): void
    {
        $http = new RecordingClient([new \GuzzleHttp\Psr7\Response(502, ['X-Request-Id' => 'req_proxy'], '<html>Bad Gateway</html>')]);
        try {
            $this->client($http, 0)->me->get();
            self::fail('expected ChannelException');
        } catch (ChannelException $e) {
            self::assertSame('unexpected_response', $e->errorCode);
            self::assertSame('req_proxy', $e->requestId);
        }

        $http = new RecordingClient([new \GuzzleHttp\Psr7\Response(200, [], 'OK')]);
        $this->expectException(PlasgosApiException::class);
        $this->client($http, 0)->me->get();
    }

    public function testResponseExposesMetaHeadersAndReplay(): void
    {
        $http = new RecordingClient([self::ok(202, ['id' => 'msg_1'], [], 'Pesan masuk antrean kirim', [
            'RateLimit-Limit' => '120', 'RateLimit-Remaining' => '119', 'RateLimit-Reset' => '42', 'Idempotent-Replayed' => 'true',
        ])]);
        $response = $this->client($http)->messages->send(['endpoint_id' => 'e', 'contact' => ['id' => 'c'], 'content' => Content::text('hai')]);

        self::assertSame('Pesan masuk antrean kirim', $response->message);
        self::assertSame(['id' => 'msg_1'], $response->data);
        self::assertSame('req_ok', $response->requestId);
        self::assertTrue($response->replayed);
        self::assertSame(['limit' => 120, 'remaining' => 119, 'reset' => 42], $response->rateLimit);
        self::assertSame('120', $response->header('RateLimit-Limit'));
    }

    public function testAutoPagingWalksAllPages(): void
    {
        $page = static fn (int $n, array $items, bool $next) => self::ok(200, $items, ['pagination' => ['page' => $n, 'per_page' => 2, 'total' => 3, 'total_pages' => 2, 'has_next_page' => $next, 'has_prev_page' => $n > 1]]);
        $http = new RecordingClient([$page(1, [['id' => 'a'], ['id' => 'b']], true), $page(2, [['id' => 'c']], false)]);

        $first = $this->client($http)->contacts->list(['per_page' => 2, 'tag' => 'vip']);
        self::assertCount(2, $first);
        self::assertSame(['a', 'b', 'c'], array_column(iterator_to_array($first->autoPaging(), false), 'id'));
        self::assertSame('page=2&per_page=2&tag=vip', $http->requests[1]->getUri()->getQuery());
    }

    public function testEmptyActionPostSendsJsonObject(): void
    {
        $http = new RecordingClient([self::ok(200, ['id' => 'b1', 'status' => 'cancelled'])]);
        $this->client($http)->broadcasts->cancel('b1');
        self::assertSame('{}', (string) $http->lastRequest()->getBody());
        self::assertSame('/api/v3/broadcasts/b1/cancel', $http->lastRequest()->getUri()->getPath());
    }

    public function testRejectsMissingCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('ak', ' ');
    }
}
