<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Resource;

use Plasgos\CrmSdk\Legacy\Envelope;

final class References extends AbstractResource
{
    /**
     * List available WABA template variables.
     *
     * @return Envelope<mixed>
     */
    public function variables(): Envelope
    {
        return $this->http->request('GET', '/api/v1/references/variables');
    }

    /**
     * @param array{page?: int, limit?: int, search?: string} $params
     * @return Envelope<mixed>
     */
    public function contacts(array $params = []): Envelope
    {
        return $this->http->request('GET', '/api/v1/references/contacts', query: self::query([
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
            'search' => $params['search'] ?? null,
        ]));
    }
}
