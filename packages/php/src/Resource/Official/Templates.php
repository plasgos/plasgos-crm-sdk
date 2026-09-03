<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource\Official;

use Plasgos\CrmSdk\Envelope;
use Plasgos\CrmSdk\Resource\AbstractResource;

final class Templates extends AbstractResource
{
    /**
     * @param array{category?: string} $params  category: MARKETING|UTILITY|AUTHENTICATION|OTP
     */
    public function list(string $accountId, array $params = []): Envelope
    {
        return $this->http->request(
            'GET',
            '/api/v1/official/templates/' . rawurlencode($accountId),
            query: self::compact(['category' => $params['category'] ?? null]),
        );
    }
}
