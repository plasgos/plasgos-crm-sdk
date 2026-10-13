<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->contacts` — scopes `contacts:read` / `contacts:write`.
 *
 * @phpstan-import-type ListContactsQuery from ApiTypes
 * @phpstan-import-type ListContactsItem from ApiTypes
 * @phpstan-import-type LookupContactQuery from ApiTypes
 * @phpstan-import-type LookupContactData from ApiTypes
 * @phpstan-import-type LookupContactMeta from ApiTypes
 * @phpstan-import-type CreateContactBody from ApiTypes
 * @phpstan-import-type CreateContactData from ApiTypes
 * @phpstan-import-type CreateContactMeta from ApiTypes
 * @phpstan-import-type UpsertContactBody from ApiTypes
 * @phpstan-import-type UpsertContactData from ApiTypes
 * @phpstan-import-type UpsertContactMeta from ApiTypes
 * @phpstan-import-type GetContactData from ApiTypes
 * @phpstan-import-type GetContactMeta from ApiTypes
 * @phpstan-import-type UpdateContactBody from ApiTypes
 * @phpstan-import-type UpdateContactData from ApiTypes
 * @phpstan-import-type UpdateContactMeta from ApiTypes
 */
final class Contacts extends AbstractResource
{
    /**
     * @param ListContactsQuery $params
     * @return Page<ListContactsItem>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListContactsItem> $page */
        $page = $this->http->requestPage('/contacts', $params, $options);
        return $page;
    }

    /**
     * Exactly one of: phone_number, email, or channel + external_id (+ endpoint_id for Instagram/Messenger).
     * Not found → NotFoundException. `meta['matched_by']`; merged contacts resolve to their target
     * (`meta['redirected_from']`).
     *
     * @param LookupContactQuery $by
     * @return Response<LookupContactData, LookupContactMeta>
     */
    public function lookup(array $by, ?RequestOptions $options = null): Response
    {
        /** @var Response<LookupContactData, LookupContactMeta> $response */
        $response = $this->http->request('GET', '/contacts/lookup', $by, options: $options);
        return $response;
    }

    /**
     * Existing phone/email → ConflictException `identity_taken` (see ->details['contact_id']).
     *
     * @param CreateContactBody $contact
     * @return Response<CreateContactData, CreateContactMeta>
     */
    public function create(array $contact, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        /** @var Response<CreateContactData, CreateContactMeta> $response */
        $response = $this->http->request('POST', '/contacts', body: $contact, idempotencyKey: $idempotencyKey, options: $options);
        return $response;
    }

    /**
     * Create (201) or update (200); `meta['created']` is true | false.
     * Tags and group ids are ADDED, never replaced.
     *
     * @param UpsertContactBody $contact may include `match_on` = phone_number | email
     * @return Response<UpsertContactData, UpsertContactMeta>
     */
    public function upsert(array $contact, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        /** @var Response<UpsertContactData, UpsertContactMeta> $response */
        $response = $this->http->request('POST', '/contacts/upsert', body: $contact, idempotencyKey: $idempotencyKey, options: $options);
        return $response;
    }

    /**
     * A merged (source) contact id returns the target contact with `meta['redirected_from']`.
     *
     * @return Response<GetContactData, GetContactMeta>
     */
    public function get(string $contactId, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetContactData, GetContactMeta> $response */
        $response = $this->http->request('GET', self::path('contacts', $contactId), options: $options);
        return $response;
    }

    /**
     * @param UpdateContactBody $changes `tags` / `group_ids` take `['add' => [], 'remove' => []]`
     * @return Response<UpdateContactData, UpdateContactMeta>
     */
    public function update(string $contactId, array $changes, ?RequestOptions $options = null): Response
    {
        /** @var Response<UpdateContactData, UpdateContactMeta> $response */
        $response = $this->http->request('PATCH', self::path('contacts', $contactId), body: $changes, options: $options);
        return $response;
    }
}
