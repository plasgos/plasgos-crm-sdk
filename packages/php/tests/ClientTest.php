<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Enum\BaseUrl;
use Plasgos\CrmSdk\Enum\Channel;
use Plasgos\CrmSdk\Exception\PlanException;
use Plasgos\CrmSdk\Exception\RateLimitException;
use Plasgos\CrmSdk\Exception\ServerException;
use Plasgos\CrmSdk\Exception\SignatureExpiredException;
use Plasgos\CrmSdk\Exception\ValidationException;
use Plasgos\CrmSdk\Tests\Support\RecordingClient;

final class ClientTest extends TestCase
{
    private function client(RecordingClient $http, int $maxRetries = 0): Client
    {
        return new Client('ak_live_123', 'sk_live_abc', new ClientConfig(
            baseUrl: BaseUrl::Sandbox,
            maxRetries: $maxRetries,
            httpClient: $http,
        ));
    }

    public function testSendsSignatureHeadersToSandboxBaseUrl(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(200, ['status' => 200, 'message' => 'ok', 'data' => [], 'metadata' => ['timestamp' => 1]]),
        ]);
        $this->client($http)->accounts->list();

        $req = $http->lastRequest();
        self::assertSame(
            'https://api-crm.sandbox.plasgos.co.id/api/v1/accounts',
            (string) $req->getUri(),
        );
        self::assertSame('ak_live_123', $req->getHeaderLine('x-api-key'));
        self::assertMatchesRegularExpression('/^\d+$/', $req->getHeaderLine('x-timestamp'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $req->getHeaderLine('x-signature'));
    }

    public function testMapsSendInputToWireBody(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(200, ['status' => 200, 'message' => 'sent', 'data' => [], 'metadata' => []]),
        ]);
        $env = $this->client($http)->messages->send([
            'message_id' => 'm-1',
            'channel' => Channel::WhatsApp,
            'account_id' => 'acc-1',
            'receiver' => ['phone_number' => '628123456789', 'name' => 'John'],
            'content' => ['type' => 'document', 'data' => [
                'url' => 'http://x/y.pdf', 'mime_type' => 'application/pdf', 'file_name' => 'y.pdf',
            ]],
        ]);

        $body = json_decode((string) $http->lastRequest()->getBody(), true);
        self::assertSame([
            'message_id' => 'm-1',
            'channel' => 'whatsapp',
            'account' => ['account_id' => 'acc-1'],
            'receiver' => ['phone_number' => '628123456789', 'name' => 'John'],
            'content' => [
                'type' => 'document',
                'data' => ['url' => 'http://x/y.pdf', 'mime_type' => 'application/pdf', 'file_name' => 'y.pdf'],
            ],
        ], $body);
        self::assertSame('m-1', $env->metadata['message_id']);
    }

    public function testGeneratesMessageIdWhenOmitted(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(200, ['status' => 200, 'message' => 'sent', 'data' => [], 'metadata' => []]),
        ]);
        $env = $this->client($http)->messages->send([
            'channel' => 'whatsapp',
            'account_id' => 'a',
            'receiver' => ['phone_number' => '62811', 'name' => 'n'],
            'content' => ['type' => 'text', 'data' => ['text' => 'hi']],
        ]);
        $body = json_decode((string) $http->lastRequest()->getBody(), true);
        self::assertMatchesRegularExpression('/[0-9a-f-]{36}/', $body['message_id']);
        self::assertSame($body['message_id'], $env->metadata['message_id']);
    }

    public function testNormalisesSuccessDataEnvelope(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(200, ['success' => true, 'data' => [['label' => 'name']]]),
        ]);
        $env = $this->client($http)->references->variables();
        self::assertNull($env->message);
        self::assertCount(1, $env->data);
    }

    public function testMapsErrors(): void
    {
        $cases = [
            [408, ['status' => 408, 'message' => 'Request timestamp expired'], SignatureExpiredException::class],
            [429, ['success' => false, 'message' => 'Batas hit API harian tercapai (100/100)'], RateLimitException::class],
            [403, ['success' => false, 'message' => 'Paket langganan Anda tidak menyertakan akses API'], PlanException::class],
        ];
        foreach ($cases as [$status, $body, $expected]) {
            $http = new RecordingClient([RecordingClient::json($status, $body)]);
            try {
                $this->client($http)->accounts->list();
                self::fail("expected {$expected}");
            } catch (\Throwable $e) {
                self::assertInstanceOf($expected, $e);
            }
        }
    }

    public function testValidationErrorCarriesIssues(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(422, [
                'status' => 422,
                'message' => 'Missing or invalid data',
                'errors' => [['message' => 'bad phone', 'path' => ['receiver', 'phone_number']]],
            ]),
        ]);
        try {
            $this->client($http)->messages->send([
                'channel' => 'whatsapp',
                'account_id' => 'a',
                'receiver' => ['phone_number' => '1', 'name' => 'n'],
                'content' => ['type' => 'text', 'data' => ['text' => 'hi']],
            ]);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['receiver', 'phone_number'], $e->errors[0]['path']);
        }
    }

    public function testBodyStatusOverHttp200StillThrows(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(200, ['status' => 404, 'message' => 'Account not found', 'data' => null]),
        ]);
        try {
            $this->client($http)->accounts->get('nope');
            self::fail('expected exception');
        } catch (\Plasgos\CrmSdk\Exception\NotFoundException $e) {
            self::assertSame(404, $e->httpStatus);
        }
    }

    public function testRetriesIdempotentGetOn500(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(500, ['status' => 500, 'message' => 'boom']),
            RecordingClient::json(200, ['status' => 200, 'message' => 'ok', 'data' => [], 'metadata' => []]),
        ]);
        $env = $this->client($http, maxRetries: 2)->accounts->list();
        self::assertSame(200, $env->httpStatus);
        self::assertCount(2, $http->requests);
    }

    public function testDoesNotRetryPost(): void
    {
        $http = new RecordingClient([
            RecordingClient::json(500, ['status' => 500, 'message' => 'boom']),
        ]);
        try {
            $this->client($http, maxRetries: 3)->messages->send([
                'channel' => 'whatsapp',
                'account_id' => 'a',
                'receiver' => ['phone_number' => '62811', 'name' => 'n'],
                'content' => ['type' => 'text', 'data' => ['text' => 'hi']],
            ]);
            self::fail('expected ServerException');
        } catch (ServerException) {
            self::assertCount(1, $http->requests);
        }
    }
}
