<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->me` — scope `account:read`.
 *
 * @phpstan-import-type GetMeData from ApiTypes
 * @phpstan-import-type GetMeMeta from ApiTypes
 */
final class Me extends AbstractResource
{
    /**
     * Tenant, key scopes and today's quota — use it for "test connection".
     *
     * @return Response<GetMeData, GetMeMeta>
     */
    public function get(?RequestOptions $options = null): Response
    {
        /** @var Response<GetMeData, GetMeMeta> $response */
        $response = $this->http->request('GET', '/me', options: $options);
        return $response;
    }
}
