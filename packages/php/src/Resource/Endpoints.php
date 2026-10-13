<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->endpoints` — the tenant's sending accounts on every channel. Scope `channels:read`.
 *
 * @phpstan-import-type ListEndpointsQuery from ApiTypes
 * @phpstan-import-type ListEndpointsItem from ApiTypes
 * @phpstan-import-type GetEndpointData from ApiTypes
 * @phpstan-import-type GetEndpointMeta from ApiTypes
 * @phpstan-import-type ListEndpointTemplatesQuery from ApiTypes
 * @phpstan-import-type ListEndpointTemplatesItem from ApiTypes
 */
final class Endpoints extends AbstractResource
{
    /**
     * @param ListEndpointsQuery $params
     * @return Page<ListEndpointsItem>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListEndpointsItem> $page */
        $page = $this->http->requestPage('/endpoints', $params, $options);
        return $page;
    }

    /**
     * @return Response<GetEndpointData, GetEndpointMeta>
     */
    public function get(string $endpointId, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetEndpointData, GetEndpointMeta> $response */
        $response = $this->http->request('GET', self::path('endpoints', $endpointId), options: $options);
        return $response;
    }

    /**
     * Approved WhatsApp templates of an official (WABA) endpoint.
     *
     * @param ListEndpointTemplatesQuery $params
     * @return Page<ListEndpointTemplatesItem>
     */
    public function templates(string $endpointId, array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListEndpointTemplatesItem> $page */
        $page = $this->http->requestPage(self::path('endpoints', $endpointId, 'templates'), $params, $options);
        return $page;
    }
}
