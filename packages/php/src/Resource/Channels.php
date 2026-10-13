<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->channels` — scope `channels:read`.
 *
 * @phpstan-import-type ListChannelsData from ApiTypes
 * @phpstan-import-type ListChannelsMeta from ApiTypes
 */
final class Channels extends AbstractResource
{
    /**
     * Known channels and whether the tenant's plan includes each.
     *
     * @return Response<ListChannelsData, ListChannelsMeta>
     */
    public function list(?RequestOptions $options = null): Response
    {
        /** @var Response<ListChannelsData, ListChannelsMeta> $response */
        $response = $this->http->request('GET', '/channels', options: $options);
        return $response;
    }
}
