<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;

/** `$client->contacts` — scopes `contacts:read` / `contacts:write`. */
final class Contacts extends AbstractResource
{
    /** @param array{search?: string, group_id?: string, tag?: string, updated_from?: string, page?: int, per_page?: int} $params */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->http->requestPage('/contacts', $params, $options);
    }

    /**
     * Exactly one of: phone_number, email, or channel + external_id (+ endpoint_id for Instagram/Messenger).
     * Not found → NotFoundException. `meta['matched_by']`; merged contacts resolve to their target
     * (`meta['redirected_from']`).
     *
     * @param array{phone_number?: string, email?: string, channel?: string, external_id?: string, endpoint_id?: string} $by
     */
    public function lookup(array $by, ?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', '/contacts/lookup', $by, options: $options);
    }

    /**
     * Existing phone/email → ConflictException `identity_taken` (see ->details['contact_id']).
     *
     * @param array<string, mixed> $contact
     */
    public function create(array $contact, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', '/contacts', body: $contact, idempotencyKey: $idempotencyKey, options: $options);
    }

    /**
     * Create (201) or update (200); `meta['created']` is true | false.
     * Tags and group ids are ADDED, never replaced.
     *
     * @param array<string, mixed> $contact may include `match_on` = phone_number | email
     */
    public function upsert(array $contact, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        return $this->http->request('POST', '/contacts/upsert', body: $contact, idempotencyKey: $idempotencyKey, options: $options);
    }

    /** A merged (source) contact id returns the target contact with `meta['redirected_from']`. */
    public function get(string $contactId, ?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', self::path('contacts', $contactId), options: $options);
    }

    /** @param array<string, mixed> $changes `tags` / `group_ids` take `['add' => [], 'remove' => []]` */
    public function update(string $contactId, array $changes, ?RequestOptions $options = null): Response
    {
        return $this->http->request('PATCH', self::path('contacts', $contactId), body: $changes, options: $options);
    }
}
