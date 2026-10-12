<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->channels` — scope `channels:read`. */
final class Channels extends AbstractResource
{
    /** Known channels and whether the tenant's plan includes each. */
    public function list(): Response
    {
        return $this->http->request('GET', '/channels');
    }
}
