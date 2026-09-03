<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Envelope;

final class Accounts extends AbstractResource
{
    public function list(): Envelope
    {
        return $this->http->request('GET', '/api/v1/accounts');
    }

    public function get(string $accountId): Envelope
    {
        return $this->http->request('GET', '/api/v1/accounts/' . rawurlencode($accountId));
    }
}
