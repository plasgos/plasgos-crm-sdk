<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\Response;

/** `$client->broadcasts` — scopes `broadcasts:read` / `broadcasts:write`. */
final class Broadcasts extends AbstractResource
{
    /** @param array{status?: string, endpoint_id?: string, page?: int, per_page?: int} $params */
    public function list(array $params = []): Page
    {
        return $this->http->requestPage('/broadcasts', $params);
    }

    /**
     * Accepted (202). Rejected recipients do not fail the request: see `meta['rejected_recipients']`.
     *
     * @param array<string, mixed> $broadcast name, endpoint_id, content, audience, scheduled_at?
     */
    public function create(array $broadcast, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', '/broadcasts', body: $broadcast, idempotencyKey: $idempotencyKey);
    }

    public function get(string $broadcastId): Response
    {
        return $this->http->request('GET', self::path('broadcasts', $broadcastId));
    }

    /** @param array{status?: string, page?: int, per_page?: int} $params */
    public function recipients(string $broadcastId, array $params = []): Page
    {
        return $this->http->requestPage(self::path('broadcasts', $broadcastId, 'recipients'), $params);
    }

    public function cancel(string $broadcastId): Response
    {
        return $this->http->request('POST', self::path('broadcasts', $broadcastId, 'cancel'), body: new \stdClass());
    }
}
