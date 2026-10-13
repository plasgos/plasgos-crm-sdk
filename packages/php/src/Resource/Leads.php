<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->leads` — scopes `leads:read` / `leads:write`. */
final class Leads extends AbstractResource
{
    /**
     * 201 new lead; 200 with `meta['created'] === false` when the contact already has an ACTIVE lead
     * (one active lead per contact across all pipelines) — that lead is returned unchanged.
     * Unknown ids → NotFoundException `pipeline_not_found` | `stage_not_found` | `contact_not_found`.
     *
     * @param array<string, mixed> $lead `contact` (one object: id | phone_number | channel + external_id | email, plus profile for a NEW contact), `pipeline_id`, `stage_id`?, …
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
