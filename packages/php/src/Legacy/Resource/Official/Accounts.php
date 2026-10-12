<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Resource\Official;

use Plasgos\CrmSdk\Legacy\Envelope;
use Plasgos\CrmSdk\Legacy\Resource\AbstractResource;

final class Accounts extends AbstractResource
{
    public function list(): Envelope
    {
        return $this->http->request('GET', '/api/v1/official/accounts');
    }

    public function phoneNumbers(string $accountId): Envelope
    {
        return $this->http->request(
            'GET',
            '/api/v1/official/accounts/phone-numbers/' . rawurlencode($accountId),
        );
    }
}
