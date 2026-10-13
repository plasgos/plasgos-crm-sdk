<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;

/** `$client->pipelines` — pipelines with their stages (read only). Scope `leads:read`. */
final class Pipelines extends AbstractResource
{
    public function list(?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', '/pipelines', options: $options);
    }
}
