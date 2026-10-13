<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->leads` — scopes `leads:read` / `leads:write`.
 *
 * @phpstan-import-type CreateLeadBody from ApiTypes
 * @phpstan-import-type CreateLeadData from ApiTypes
 * @phpstan-import-type CreateLeadMeta from ApiTypes
 * @phpstan-import-type GetLeadData from ApiTypes
 * @phpstan-import-type GetLeadMeta from ApiTypes
 */
final class Leads extends AbstractResource
{
    /**
     * 201 new lead; 200 with `meta['created'] === false` when the contact already has an ACTIVE lead
     * (one active lead per contact across all pipelines) — that lead is returned unchanged.
     * Unknown ids → NotFoundException `pipeline_not_found` | `stage_not_found` | `contact_not_found`.
     *
     * @param CreateLeadBody $lead `contact` (one object: id | phone_number | channel + external_id | email, plus profile for a NEW contact), `pipeline_id`, `stage_id`?, …
     * @return Response<CreateLeadData, CreateLeadMeta>
     */
    public function create(array $lead, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        /** @var Response<CreateLeadData, CreateLeadMeta> $response */
        $response = $this->http->request('POST', '/leads', body: $lead, idempotencyKey: $idempotencyKey, options: $options);
        return $response;
    }

    /**
     * @return Response<GetLeadData, GetLeadMeta>
     */
    public function get(string $leadId, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetLeadData, GetLeadMeta> $response */
        $response = $this->http->request('GET', self::path('leads', $leadId), options: $options);
        return $response;
    }
}
