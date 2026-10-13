<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Resource\Official;

use Plasgos\CrmSdk\Legacy\Envelope;
use Plasgos\CrmSdk\Legacy\Resource\AbstractResource;

final class Templates extends AbstractResource
{
    /**
     * @param array{category?: string} $params  category: MARKETING|UTILITY|AUTHENTICATION|OTP
     * @return Envelope<mixed>
     */
    public function list(string $accountId, array $params = []): Envelope
    {
        return $this->http->request(
            'GET',
            '/api/v1/official/templates/' . rawurlencode($accountId),
            query: self::query(['category' => $params['category'] ?? null]),
        );
    }
}
