<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;

/** `$client->broadcasts` — scopes `broadcasts:read` / `broadcasts:write`. */
final class Broadcasts extends AbstractResource
{
    /** @param array{status?: string, endpoint_id?: string, page?: int, per_page?: int} $params */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->http->requestPage('/broadcasts', $params, $options);
    }

    /**
     * Accepted (202). v3.0 broadcasts official WhatsApp templates only (`content.type = template`;
     * other endpoints → ValidationException `channel_broadcast_unsupported`). `audience` is exactly one
     * of `recipients` / `contact_ids` / `group_ids`, max 1000 recipients (the integration daily quota —
     * RateLimitException `daily_broadcast_limit_exceeded`). Rejected recipients do not fail the
     * request: see `meta['rejected_recipients']` and `meta['rejected_count']`.
     *
     * @param array<string, mixed> $broadcast name, endpoint_id, content, audience, scheduled_at?, description?
     */
    public function create(array $broadcast, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', '/broadcasts', body: $broadcast, idempotencyKey: $idempotencyKey, options: $options);
    }

    public function get(string $broadcastId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', self::path('broadcasts', $broadcastId), options: $options);
    }

    /** @param array{status?: string, page?: int, per_page?: int} $params */
    public function recipients(string $broadcastId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->http->requestPage(self::path('broadcasts', $broadcastId, 'recipients'), $params, $options);
    }

    /** Completed/cancelled broadcasts → ConflictException `broadcast_not_cancellable`. */
    public function cancel(string $broadcastId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', self::path('broadcasts', $broadcastId, 'cancel'), body: new \stdClass(), options: $options);
    }
}
