<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->me` — scope `account:read`. */
final class Me extends AbstractResource
{
    /** Tenant, key scopes and today's quota — use it for "test connection". */
    public function get(): Response
    {
        return $this->http->request('GET', '/me');
    }
}
