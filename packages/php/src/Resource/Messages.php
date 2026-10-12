<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\Response;

/** `$client->messages` — omnichannel sending. Scopes `messages:read` / `messages:write`. */
final class Messages extends AbstractResource
{
    /**
     * Which endpoints can reach this recipient, and whether a template is required.
     * Name the recipient exactly one way.
     *
     * @param array{contact_id?: string, phone_number?: string, channel?: string, external_id?: string} $recipient
     */
    public function sendOptions(array $recipient): Response
    {
        return $this->http->request('GET', '/messages/send-options', $recipient);
    }

    /**
     * Send one message (202). Final status arrives via get() or the `message.status_updated` webhook.
     *
     * @param array{endpoint_id: string, recipient: array<string, mixed>, content: array<string, mixed>|\JsonSerializable, create_contact?: array<string, mixed>|null, client_reference?: string|null} $input
     */
    public function send(array $input, ?string $idempotencyKey = null): Response
    {
        return $this->http->request('POST', '/messages', body: $input, idempotencyKey: $idempotencyKey);
    }

    public function get(string $messageId): Response
    {
        return $this->http->request('GET', self::path('messages', $messageId));
    }

    /** @param array<string, scalar|null> $params contact_id, endpoint_id, status, client_reference, created_from, created_to, page, per_page */
    public function list(array $params = []): Page
    {
        return $this->http->requestPage('/messages', $params);
    }
}
