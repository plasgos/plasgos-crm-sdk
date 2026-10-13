<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->webhooks` — subscriptions for this tenant. Scope `webhooks:manage`.
 *
 * @phpstan-import-type ListWebhooksData from ApiTypes
 * @phpstan-import-type ListWebhooksMeta from ApiTypes
 * @phpstan-import-type CreateWebhookBody from ApiTypes
 * @phpstan-import-type CreateWebhookData from ApiTypes
 * @phpstan-import-type CreateWebhookMeta from ApiTypes
 * @phpstan-import-type UpdateWebhookBody from ApiTypes
 * @phpstan-import-type UpdateWebhookData from ApiTypes
 * @phpstan-import-type UpdateWebhookMeta from ApiTypes
 * @phpstan-import-type DeleteWebhookData from ApiTypes
 * @phpstan-import-type DeleteWebhookMeta from ApiTypes
 * @phpstan-import-type TestWebhookData from ApiTypes
 * @phpstan-import-type TestWebhookMeta from ApiTypes
 * @phpstan-import-type RotateWebhookSecretData from ApiTypes
 * @phpstan-import-type RotateWebhookSecretMeta from ApiTypes
 */
final class Webhooks extends AbstractResource
{
    /**
     * @return Response<ListWebhooksData, ListWebhooksMeta>
     */
    public function list(?RequestOptions $options = null): Response
    {
        /** @var Response<ListWebhooksData, ListWebhooksMeta> $response */
        $response = $this->http->request('GET', '/webhooks', options: $options);
        return $response;
    }

    /**
     * `data['signing_secret']` is returned ONLY here — store it. The URL must be https and public
     * (ValidationException `webhook_url_invalid`; details in `->fields`), max 5 per tenant
     * (ConflictException `webhook_limit_reached`). `webhook.test` cannot be subscribed to.
     *
     * @param CreateWebhookBody $subscription
     * @return Response<CreateWebhookData, CreateWebhookMeta>
     */
    public function create(array $subscription, ?RequestOptions $options = null): Response
    {
        /** @var Response<CreateWebhookData, CreateWebhookMeta> $response */
        $response = $this->http->request('POST', '/webhooks', body: $subscription, options: $options);
        return $response;
    }

    /**
     * @param UpdateWebhookBody $changes
     * @return Response<UpdateWebhookData, UpdateWebhookMeta>
     */
    public function update(string $webhookId, array $changes, ?RequestOptions $options = null): Response
    {
        /** @var Response<UpdateWebhookData, UpdateWebhookMeta> $response */
        $response = $this->http->request('PATCH', self::path('webhooks', $webhookId), body: $changes, options: $options);
        return $response;
    }

    /**
     * @return Response<DeleteWebhookData, DeleteWebhookMeta>
     */
    public function delete(string $webhookId, ?RequestOptions $options = null): Response
    {
        /** @var Response<DeleteWebhookData, DeleteWebhookMeta> $response */
        $response = $this->http->request('DELETE', self::path('webhooks', $webhookId), options: $options);
        return $response;
    }

    /**
     * Ask the CRM to deliver a `webhook.test` event now.
     *
     * @return Response<TestWebhookData, TestWebhookMeta>
     */
    public function test(string $webhookId, ?RequestOptions $options = null): Response
    {
        /** @var Response<TestWebhookData, TestWebhookMeta> $response */
        $response = $this->http->request('POST', self::path('webhooks', $webhookId, 'test'), body: new \stdClass(), options: $options);
        return $response;
    }

    /**
     * New signing secret; the previous one stays valid for 24 hours — deliveries carry both
     * signatures meanwhile (`v1=new,v1=old`), which `Webhook::verify()` accepts.
     *
     * @return Response<RotateWebhookSecretData, RotateWebhookSecretMeta>
     */
    public function rotateSecret(string $webhookId, ?RequestOptions $options = null): Response
    {
        /** @var Response<RotateWebhookSecretData, RotateWebhookSecretMeta> $response */
        $response = $this->http->request('POST', self::path('webhooks', $webhookId, 'rotate-secret'), body: new \stdClass(), options: $options);
        return $response;
    }
}
