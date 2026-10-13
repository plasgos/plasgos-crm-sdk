<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;

/** `$client->endpoints` — the tenant's sending accounts on every channel. Scope `channels:read`. */
final class Endpoints extends AbstractResource
{
    /** @param array{channel?: string, transport?: string, ready?: bool, page?: int, per_page?: int} $params */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->http->requestPage('/endpoints', $params, $options);
    }

    public function get(string $endpointId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', self::path('endpoints', $endpointId), options: $options);
    }

    /**
     * Approved WhatsApp templates of an official (WABA) endpoint.
     *
     * @param array{category?: string, search?: string, page?: int, per_page?: int} $params
     */
    public function templates(string $endpointId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->http->requestPage(self::path('endpoints', $endpointId, 'templates'), $params, $options);
    }
}
