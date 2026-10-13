<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->messages` — omnichannel sending. Scopes `messages:read` / `messages:write`.
 *
 * @phpstan-import-type GetMessageSendOptionsQuery from ApiTypes
 * @phpstan-import-type GetMessageSendOptionsData from ApiTypes
 * @phpstan-import-type GetMessageSendOptionsMeta from ApiTypes
 * @phpstan-import-type SendMessageBody from ApiTypes
 * @phpstan-import-type SendMessageData from ApiTypes
 * @phpstan-import-type SendMessageMeta from ApiTypes
 * @phpstan-import-type GetMessageData from ApiTypes
 * @phpstan-import-type GetMessageMeta from ApiTypes
 */
final class Messages extends AbstractResource
{
    /**
     * Which endpoints can reach this contact, and whether a template is required.
     * Name the contact exactly one way (contact_id, phone_number, channel + external_id, or email); `endpoint_id` goes with `channel` + `external_id` for
     * Instagram/Messenger identities.
     *
     * @param GetMessageSendOptionsQuery $contact
     * @return Response<GetMessageSendOptionsData, GetMessageSendOptionsMeta>
     */
    public function sendOptions(array $contact, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetMessageSendOptionsData, GetMessageSendOptionsMeta> $response */
        $response = $this->http->request('GET', '/messages/send-options', $contact, options: $options);
        return $response;
    }

    /**
     * Send one message (202). A message the channel rejected is still 202 with `status = failed`
     * and `error`. Final status arrives via get() or the `message.status_updated` webhook.
     *
     * @param SendMessageBody $input
     * @return Response<SendMessageData, SendMessageMeta>
     */
    public function send(array $input, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        /** @var Response<SendMessageData, SendMessageMeta> $response */
        $response = $this->http->request('POST', '/messages', body: $input, idempotencyKey: $idempotencyKey, options: $options);
        return $response;
    }

    /**
     * One message by the id returned from send() (the channel message id).
     *
     * @return Response<GetMessageData, GetMessageMeta>
     */
    public function get(string $messageId, ?RequestOptions $options = null): Response
    {
        /** @var Response<GetMessageData, GetMessageMeta> $response */
        $response = $this->http->request('GET', self::path('messages', $messageId), options: $options);
        return $response;
    }
}
