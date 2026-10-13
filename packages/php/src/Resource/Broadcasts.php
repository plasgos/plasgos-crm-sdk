<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->broadcasts` — scopes `broadcasts:read` / `broadcasts:write`.
 *
 * @phpstan-import-type ListBroadcastsQuery from ApiTypes
 * @phpstan-import-type ListBroadcastsItem from ApiTypes
 * @phpstan-import-type CreateBroadcastBody from ApiTypes
 * @phpstan-import-type CreateBroadcastData from ApiTypes
 * @phpstan-import-type CreateBroadcastMeta from ApiTypes
 * @phpstan-import-type GetBroadcastData from ApiTypes
 * @phpstan-import-type GetBroadcastMeta from ApiTypes
 * @phpstan-import-type ListBroadcastRecipientsQuery from ApiTypes
 * @phpstan-import-type ListBroadcastRecipientsItem from ApiTypes
 * @phpstan-import-type CancelBroadcastData from ApiTypes
 * @phpstan-import-type CancelBroadcastMeta from ApiTypes
 */
final class Broadcasts extends AbstractResource
{
    /**
     * @param ListBroadcastsQuery $params
     * @return Page<ListBroadcastsItem>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListBroadcastsItem> $page */
        $page = $this->http->requestPage('/broadcasts', $params, $options);
        return $page;
    }

    /**
     * Accepted (202). v3.0 broadcasts official WhatsApp templates only (`content.type = template`;
     * other endpoints → ValidationException `channel_broadcast_unsupported`). `audience` is exactly one
     * of `recipients` / `contact_ids` / `group_ids`, max 1000 recipients (the integration daily quota —
     * RateLimitException `daily_broadcast_limit_exceeded`). Rejected recipients do not fail the
     * request: see `meta['rejected_recipients']` and `meta['rejected_count']`.
     *
     * @param CreateBroadcastBody $broadcast name, endpoint_id, content, audience, scheduled_at?, description?
     * @return Response<CreateBroadcastData, CreateBroadcastMeta>
     */
    public function create(array $broadcast, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        /** @var Response<CreateBroadcastData, CreateBroadcastMeta> $response */
        $response = $this->http->request('POST', '/broadcasts', body: $broadcast, idempotencyKey: $idempotencyKey, options: $options);
        return $response;
    }

    /**
     * @return Response<GetBroadcastData, GetBroadcastMeta>
     */
    public function get(string $broadcastId, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetBroadcastData, GetBroadcastMeta> $response */
        $response = $this->http->request('GET', self::path('broadcasts', $broadcastId), options: $options);
        return $response;
    }

    /**
     * @param ListBroadcastRecipientsQuery $params
     * @return Page<ListBroadcastRecipientsItem>
     */
    public function recipients(string $broadcastId, array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListBroadcastRecipientsItem> $page */
        $page = $this->http->requestPage(self::path('broadcasts', $broadcastId, 'recipients'), $params, $options);
        return $page;
    }

    /**
     * Completed/cancelled broadcasts → ConflictException `broadcast_not_cancellable`.
     *
     * @return Response<CancelBroadcastData, CancelBroadcastMeta>
     */
    public function cancel(string $broadcastId, ?RequestOptions $options = null): Response
    {
        /** @var Response<CancelBroadcastData, CancelBroadcastMeta> $response */
        $response = $this->http->request('POST', self::path('broadcasts', $broadcastId, 'cancel'), body: new \stdClass(), options: $options);
        return $response;
    }
}
