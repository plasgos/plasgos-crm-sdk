<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->leads` — scopes `leads:read` / `leads:write`. */
final class Leads extends AbstractResource
{
    /**
     * 201 new lead; 200 with `data['created'] === false` when the contact already has an open lead
     * in that pipeline.
     *
     * @param array<string, mixed> $lead `contact_id` OR `contact`, `pipeline_id`, `stage_id`?, …
     */
    public function create(array $lead, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', '/leads', body: $lead, idempotencyKey: $idempotencyKey);
    }

    public function get(string $leadId): Response
    {
        return $this->http->request('GET', self::path('leads', $leadId));
    }
}
