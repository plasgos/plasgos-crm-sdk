<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;

/** `$client->webhooks` — subscriptions for this tenant. Scope `webhooks:manage`. */
final class Webhooks extends AbstractResource
{
    public function list(?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', '/webhooks', options: $options);
    }

    /**
     * `data['signing_secret']` is returned ONLY here — store it. The URL must be https and public
     * (ValidationException `webhook_url_invalid`; details in `->fields`), max 5 per tenant
     * (ConflictException `webhook_limit_reached`). `webhook.test` cannot be subscribed to.
     *
     * @param array{url: string, events: list<string>, description?: string|null} $subscription
     */
    public function create(array $subscription, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', '/webhooks', body: $subscription, options: $options);
    }

    /** @param array{url?: string, events?: list<string>, description?: string|null, enabled?: bool} $changes */
    public function update(string $webhookId, array $changes, ?RequestOptions $options = null): Response
    {
        return $this->http->request('PATCH', self::path('webhooks', $webhookId), body: $changes, options: $options);
    }

    public function delete(string $webhookId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('DELETE', self::path('webhooks', $webhookId), options: $options);
    }

    /** Ask the CRM to deliver a `webhook.test` event now. */
    public function test(string $webhookId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', self::path('webhooks', $webhookId, 'test'), body: new \stdClass(), options: $options);
    }

    /**
     * New signing secret; the previous one stays valid for 24 hours — deliveries carry both
     * signatures meanwhile (`v1=new,v1=old`), which `Webhook::verify()` accepts.
     */
    public function rotateSecret(string $webhookId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', self::path('webhooks', $webhookId, 'rotate-secret'), body: new \stdClass(), options: $options);
    }
}
