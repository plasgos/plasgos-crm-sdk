<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->messages` — omnichannel sending. Scopes `messages:read` / `messages:write`. */
final class Messages extends AbstractResource
{
    /**
     * Which endpoints can reach this contact, and whether a template is required.
     * Name the contact exactly one way (contact_id, phone_number, channel + external_id, or email); `endpoint_id` goes with `channel` + `external_id` for
     * Instagram/Messenger identities.
     *
     * @param array{contact_id?: string, phone_number?: string, channel?: string, external_id?: string, email?: string, endpoint_id?: string} $contact
     */
    public function sendOptions(array $contact): Response
    {
        return $this->http->request('GET', '/messages/send-options', $contact);
    }

    /**
     * Send one message (202). A message the channel rejected is still 202 with `status = failed`
     * and `error`. Final status arrives via get() or the `message.status_updated` webhook.
     *
     * @param array{endpoint_id: string, contact: array<string, mixed>, content: array<string, mixed>|\JsonSerializable, client_reference?: string|null} $input
     */
    public function send(array $input, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', '/messages', body: $input, idempotencyKey: $idempotencyKey);
    }

    /** One message by the id returned from send() (the channel message id). */
    public function get(string $messageId): Response
    {
        return $this->http->request('GET', self::path('messages', $messageId));
    }
}
