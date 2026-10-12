<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Exception\AuthenticationException;

/**
 * Optional smoke test against a real API v3 server. Skipped unless these are set:
 *   PLASGOS_LIVE_BASE_URL  e.g. https://api-crm.sandbox.plasgos.co.id or http://127.0.0.1:3001
 *   PLASGOS_LIVE_API_KEY, PLASGOS_LIVE_SECRET_KEY
 */
final class LiveTest extends TestCase
{
    private function config(): ClientConfig
    {
        $base = getenv('PLASGOS_LIVE_BASE_URL');
        if (! $base || ! getenv('PLASGOS_LIVE_API_KEY') || ! getenv('PLASGOS_LIVE_SECRET_KEY')) {
            self::markTestSkipped('PLASGOS_LIVE_* not set');
        }
        return new ClientConfig(baseUrl: $base, maxRetries: 0);
    }

    public function testMeAgainstRealServer(): void
    {
        $client = new Client((string) getenv('PLASGOS_LIVE_API_KEY'), (string) getenv('PLASGOS_LIVE_SECRET_KEY'), $this->config());
        $me = $client->me->get();
        self::assertSame(200, $me->httpStatus);
        self::assertContains('account:read', $me->data['scopes']);
        self::assertMatchesRegularExpression('/^req_/', (string) $me->requestId);
        self::assertSame(200, $client->request('GET', '/me', ['search' => 'Budi & Sari/+62 👋', 'page' => 2])->httpStatus, 'query with reserved & unicode chars');
    }

    public function testWrongSecretIsRejected(): void
    {
        $client = new Client((string) getenv('PLASGOS_LIVE_API_KEY'), 'plg_wrong', $this->config());
        try {
            $client->me->get();
            self::fail('expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame('signature_invalid', $e->errorCode);
        }
    }
}
