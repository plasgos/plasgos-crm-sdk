<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Content;
use Plasgos\CrmSdk\Exception\AuthenticationException;
use Plasgos\CrmSdk\Exception\NotFoundException;
use Plasgos\CrmSdk\Exception\ValidationException;

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

    /** V2 — read-only paths only: nothing here can deliver a message to a real customer. */
    public function testChannelsAndMessagesReadPaths(): void
    {
        $client = new Client((string) getenv('PLASGOS_LIVE_API_KEY'), (string) getenv('PLASGOS_LIVE_SECRET_KEY'), $this->config());

        $channels = $client->channels->list();
        self::assertNotEmpty($channels->data);
        $endpoints = $client->endpoints->list(['per_page' => 5]);
        self::assertArrayHasKey('total', $endpoints->pagination);
        foreach ($endpoints as $endpoint) {
            self::assertContains($endpoint['status'], ['ready', 'not_ready', 'disconnected']);
        }

        $options = $client->messages->sendOptions(['phone_number' => '6280000000001']);
        self::assertNull($options->data['contact']);
        self::assertIsArray($options->data['options']);

        try {
            $client->messages->get('wamid.sdk-live-test-missing');
            self::fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('message_not_found', $e->errorCode);
        }

        // Two recipients at once is rejected before anything is sent.
        try {
            $client->messages->send(['endpoint_id' => 'x', 'recipient' => ['contact_id' => 'a', 'phone_number' => '081234567890'], 'content' => Content::text('uji')]);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('validation_failed', $e->errorCode);
            self::assertSame('recipient', $e->fields[0]['field']);
        }
    }

    /** V3 — read paths + validation only; skipped when the key lacks contacts/leads scopes. */
    public function testContactsAndLeadsReadPaths(): void
    {
        $client = new Client((string) getenv('PLASGOS_LIVE_API_KEY'), (string) getenv('PLASGOS_LIVE_SECRET_KEY'), $this->config());
        $scopes = $client->me->get()->data['scopes'] ?? [];
        if (! in_array('contacts:read', $scopes, true) || ! in_array('leads:read', $scopes, true)) {
            self::markTestSkipped('key has no contacts:read / leads:read scope');
        }

        $pipelines = $client->pipelines->list();
        foreach ($pipelines->data as $pipeline) {
            foreach ($pipeline['stages'] as $stage) {
                self::assertContains($stage['kind'], ['open', 'won', 'lost']);
            }
        }
        $contacts = $client->contacts->list(['per_page' => 1, 'updated_from' => '2026-01-01T00:00:00Z']);
        self::assertArrayHasKey('total', $contacts->pagination);

        try {
            $client->contacts->lookup(['email' => 'sdk-live-test-missing@contoh.invalid']);
            self::fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('contact_not_found', $e->errorCode);
        }
        try {
            $client->leads->get('66f1dddddddddddddddddd99');
            self::fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('lead_not_found', $e->errorCode);
        }
        try {
            $client->contacts->create(['name' => 'Tanpa nomor']);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('validation_failed', $e->errorCode);
        }
    }

    /** V4 — read paths + validation only; skipped when the key lacks broadcasts/followups scopes. */
    public function testBroadcastsAndFollowupsReadPaths(): void
    {
        $client = new Client((string) getenv('PLASGOS_LIVE_API_KEY'), (string) getenv('PLASGOS_LIVE_SECRET_KEY'), $this->config());
        $scopes = $client->me->get()->data['scopes'] ?? [];
        if (! in_array('broadcasts:read', $scopes, true) || ! in_array('followups:read', $scopes, true)) {
            self::markTestSkipped('key has no broadcasts:read / followups:read scope');
        }

        $broadcasts = $client->broadcasts->list(['per_page' => 1]);
        self::assertArrayHasKey('total', $broadcasts->pagination);
        $followups = $client->followups->list(['per_page' => 1]);
        self::assertArrayHasKey('total', $followups->pagination);

        try {
            $client->broadcasts->get('66f1eeeeeeeeeeeeeeeeee99');
            self::fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('broadcast_not_found', $e->errorCode);
        }
        try {
            $client->followups->get('66f1ffffffffffffffffff99');
            self::fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('followup_not_found', $e->errorCode);
        }
        if (in_array('broadcasts:write', $scopes, true)) {
            try {
                $client->broadcasts->create(['name' => 'x', 'endpoint_id' => 'x', 'content' => Content::text('uji'), 'audience' => ['recipients' => [['phone_number' => '081234567890']]]]);
                self::fail('expected ValidationException');
            } catch (ValidationException $e) {
                self::assertSame('validation_failed', $e->errorCode);
            }
        }
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
