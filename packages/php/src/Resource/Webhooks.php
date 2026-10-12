<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->webhooks` — subscriptions for this tenant. Scope `webhooks:manage`. */
final class Webhooks extends AbstractResource
{
    public function list(): Response
    {
        return $this->http->request('GET', '/webhooks');
    }

    /**
     * `data['signing_secret']` is returned ONLY here — store it.
     *
     * @param array{url: string, events: list<string>, description?: string|null} $subscription
     */
    public function create(array $subscription): Response
    {
        return $this->http->request('POST', '/webhooks', body: $subscription);
    }

    /** @param array{url?: string, events?: list<string>, description?: string|null, enabled?: bool} $changes */
    public function update(string $webhookId, array $changes): Response
    {
        return $this->http->request('PATCH', self::path('webhooks', $webhookId), body: $changes);
    }

    public function delete(string $webhookId): Response
    {
        return $this->http->request('DELETE', self::path('webhooks', $webhookId));
    }

    /** Ask the CRM to deliver a `webhook.test` event now. */
    public function test(string $webhookId): Response
    {
        return $this->http->request('POST', self::path('webhooks', $webhookId, 'test'), body: new \stdClass());
    }

    /** New signing secret; the previous one stays valid for 24 hours. */
    public function rotateSecret(string $webhookId): Response
    {
        return $this->http->request('POST', self::path('webhooks', $webhookId, 'rotate-secret'), body: new \stdClass());
    }
}
