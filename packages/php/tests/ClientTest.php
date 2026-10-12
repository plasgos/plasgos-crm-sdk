<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

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
use Plasgos\CrmSdk\Signature;
use Plasgos\CrmSdk\Tests\Support\RecordingClient;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

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
            'recipient' => ['phone_number' => '081234567890'],
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
            self::ok(201, ['id' => 'c1'], ['operation' => 'created']),
        ]);
        $response = $this->client($http)->contacts->upsert(['phone_number' => '0812']);

        self::assertSame(201, $response->httpStatus);
        self::assertSame('created', $response->meta['operation']);
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
            $this->client($http)->leads->create(['contact_id' => 'c', 'pipeline_id' => 'p']);
            self::fail('expected ServerException');
        } catch (ServerException $e) {
            self::assertCount(1, $http->requests, 'a 500 POST may have partially run — not retried');
        }
    }

    public function testClientErrorsAreNeverRetried(): void
    {
        $http = new RecordingClient([self::error(422, 'requires_template', 'invalid_request')]);
        try {
            $this->client($http)->messages->send(['endpoint_id' => 'e', 'recipient' => ['contact_id' => 'c'], 'content' => Content::text('x')]);
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
        $fields = $status === 400 ? [] : [['field' => 'recipient.phone_number', 'code' => 'required', 'message' => 'wajib']];
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
                self::assertSame('recipient.phone_number', $e->fields[0]['field']);
                self::assertSame(['recipient', 'phone_number'], $e->errors[0]['path'], 'legacy ->errors view kept');
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
        $response = $this->client($http)->messages->send(['endpoint_id' => 'e', 'recipient' => ['contact_id' => 'c'], 'content' => Content::text('hai')]);

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
