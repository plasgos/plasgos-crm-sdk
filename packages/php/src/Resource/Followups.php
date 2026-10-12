<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\Response;

/**
 * `$client->followups` — sequences are designed in the CRM dashboard; integrations only enrol and
 * unenrol contacts. Scopes `followups:read` / `followups:write`.
 */
final class Followups extends AbstractResource
{
    /** @param array{status?: string, page?: int, per_page?: int} $params */
    public function list(array $params = []): Page
    {
        return $this->http->requestPage('/followups', $params);
    }

    public function get(string $followupId): Response
    {
        return $this->http->request('GET', self::path('followups', $followupId));
    }

    /** @param array{status?: string, contact_id?: string, page?: int, per_page?: int} $params */
    public function enrollments(string $followupId, array $params = []): Page
    {
        return $this->http->requestPage(self::path('followups', $followupId, 'enrollments'), $params);
    }

    /**
     * 201 enrolled; 200 when already actively enrolled.
     *
     * @param array<string, mixed> $enrollment `contact_id` OR `contact`, start_at?, variables?, client_reference?
     */
    public function enroll(string $followupId, array $enrollment, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', self::path('followups', $followupId, 'enrollments'), body: $enrollment, idempotencyKey: $idempotencyKey);
    }

    /** Stop the sequence for one contact (idempotent). */
    public function unenroll(string $followupId, string $contactId): Response
    {
        return $this->http->request('DELETE', self::path('followups', $followupId, 'enrollments', $contactId));
    }
}
