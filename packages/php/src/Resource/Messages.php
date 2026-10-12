<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Response;

/** `$client->messages` — omnichannel sending. Scopes `messages:read` / `messages:write`. */
final class Messages extends AbstractResource
{
    /**
     * Which endpoints can reach this recipient, and whether a template is required.
     * Name the recipient exactly one way; `endpoint_id` goes with `channel` + `external_id` for
     * Instagram/Messenger identities.
     *
     * @param array{contact_id?: string, phone_number?: string, channel?: string, external_id?: string, endpoint_id?: string} $recipient
     */
    public function sendOptions(array $recipient): Response
    {
        return $this->http->request('GET', '/messages/send-options', $recipient);
    }

    /**
     * Send one message (202). A message the channel rejected is still 202 with `status = failed`
     * and `error`. Final status arrives via get() or the `message.status_updated` webhook.
     *
     * @param array{endpoint_id: string, recipient: array<string, mixed>, content: array<string, mixed>|\JsonSerializable, create_contact?: array<string, mixed>|null, client_reference?: string|null} $input
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
