<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Resource;

use Plasgos\CrmSdk\Legacy\Envelope;

final class Accounts extends AbstractResource
{
    /** @return Envelope<mixed> */
    public function list(): Envelope
    {
        return $this->http->request('GET', '/api/v1/accounts');
    }

    /** @return Envelope<mixed> */
    public function get(string $accountId): Envelope
    {
        return $this->http->request('GET', '/api/v1/accounts/' . rawurlencode($accountId));
    }
}
