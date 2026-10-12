<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\Response;

/** `$client->contacts` — scopes `contacts:read` / `contacts:write`. */
final class Contacts extends AbstractResource
{
    /** @param array{search?: string, group_id?: string, tag?: string, updated_from?: string, page?: int, per_page?: int} $params */
    public function list(array $params = []): Page
    {
        return $this->http->requestPage('/contacts', $params);
    }

    /**
     * Exactly one of: phone_number, email, or channel + external_id. Not found → NotFoundException.
     *
     * @param array{phone_number?: string, email?: string, channel?: string, external_id?: string} $by
     */
    public function lookup(array $by): Response
    {
        return $this->http->request('GET', '/contacts/lookup', $by);
    }

    /**
     * Existing phone/email → ConflictException `contact_exists` (see ->details['contact_id']).
     *
     * @param array<string, mixed> $contact
     */
    public function create(array $contact, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', '/contacts', body: $contact, idempotencyKey: $idempotencyKey);
    }

    /**
     * Create (201) or update (200); `meta['operation']` is `created` | `updated`.
     * Tags and group ids are ADDED, never replaced.
     *
     * @param array<string, mixed> $contact may include `match_on` = phone_number | email
     */
    public function upsert(array $contact, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', '/contacts/upsert', body: $contact, idempotencyKey: $idempotencyKey);
    }

    public function get(string $contactId): Response
    {
        return $this->http->request('GET', self::path('contacts', $contactId));
    }

    /** @param array<string, mixed> $changes `tags` / `group_ids` take `['add' => [], 'remove' => []]` */
    public function update(string $contactId, array $changes): Response
    {
        return $this->http->request('PATCH', self::path('contacts', $contactId), body: $changes);
    }
}
