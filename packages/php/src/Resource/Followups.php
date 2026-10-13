<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->followups` — sequences are designed in the CRM dashboard; integrations only enrol and
 * unenrol contacts. Scopes `followups:read` / `followups:write`.
 *
 * @phpstan-import-type ListFollowupsQuery from ApiTypes
 * @phpstan-import-type ListFollowupsItem from ApiTypes
 * @phpstan-import-type GetFollowupData from ApiTypes
 * @phpstan-import-type GetFollowupMeta from ApiTypes
 * @phpstan-import-type ListFollowupEnrollmentsQuery from ApiTypes
 * @phpstan-import-type ListFollowupEnrollmentsItem from ApiTypes
 * @phpstan-import-type EnrollFollowupBody from ApiTypes
 * @phpstan-import-type EnrollFollowupData from ApiTypes
 * @phpstan-import-type EnrollFollowupMeta from ApiTypes
 * @phpstan-import-type UnenrollFollowupData from ApiTypes
 * @phpstan-import-type UnenrollFollowupMeta from ApiTypes
 */
final class Followups extends AbstractResource
{
    /**
     * @param ListFollowupsQuery $params
     * @return Page<ListFollowupsItem>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListFollowupsItem> $page */
        $page = $this->http->requestPage('/followups', $params, $options);
        return $page;
    }

    /**
     * @return Response<GetFollowupData, GetFollowupMeta>
     */
    public function get(string $followupId, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetFollowupData, GetFollowupMeta> $response */
        $response = $this->http->request('GET', self::path('followups', $followupId), options: $options);
        return $response;
    }

    /**
     * @param ListFollowupEnrollmentsQuery $params
     * @return Page<ListFollowupEnrollmentsItem>
     */
    public function enrollments(string $followupId, array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListFollowupEnrollmentsItem> $page */
        $page = $this->http->requestPage(self::path('followups', $followupId, 'enrollments'), $params, $options);
        return $page;
    }

    /**
     * 201 enrolled; 200 with `meta['created'] === false` when the contact is still actively enrolled
     * (never scheduled twice). Inactive sequence → ConflictException `followup_not_active`.
     *
     * @param EnrollFollowupBody $enrollment `contact` (one object: id | phone_number | channel + external_id | email, plus profile for a NEW contact), start_at?, client_reference?
     * @return Response<EnrollFollowupData, EnrollFollowupMeta>
     */
    public function enroll(string $followupId, array $enrollment, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        /** @var Response<EnrollFollowupData, EnrollFollowupMeta> $response */
        $response = $this->http->request('POST', self::path('followups', $followupId, 'enrollments'), body: $enrollment, idempotencyKey: $idempotencyKey, options: $options);
        return $response;
    }

    /**
     * Stop the sequence for one contact; queued messages are removed (idempotent, `meta['stopped_messages']`).
     * A contact that was never enrolled → NotFoundException `enrollment_not_found`.
     *
     * @return Response<UnenrollFollowupData, UnenrollFollowupMeta>
     */
    public function unenroll(string $followupId, string $contactId, ?RequestOptions $options = null): Response
    {
        /** @var Response<UnenrollFollowupData, UnenrollFollowupMeta> $response */
        $response = $this->http->request('DELETE', self::path('followups', $followupId, 'enrollments', $contactId), options: $options);
        return $response;
    }
}
