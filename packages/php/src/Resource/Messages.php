<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Enum\Channel;
use Plasgos\CrmSdk\Enum\ContentType;
use Plasgos\CrmSdk\Envelope;

final class Messages extends AbstractResource
{
    /**
     * Send an unofficial WhatsApp message.
     *
     * @param array{
     *     message_id?: string,
     *     channel: Channel|string,
     *     account_id: string,
     *     receiver: array{phone_number: string, name: string},
     *     content: array{type: ContentType|string, data: array<string, mixed>}
     * } $input
     *
     * If `message_id` is omitted a UUIDv4 is generated. The value actually sent
     * is available as `$envelope->metadata['message_id']`.
     */
    public function send(array $input): Envelope
    {
        $messageId = $input['message_id'] ?? self::uuid4();
        $channel = $input['channel'] instanceof Channel ? $input['channel']->value : $input['channel'];
        $type = ($input['content']['type'] ?? null) instanceof ContentType
            ? $input['content']['type']->value
            : ($input['content']['type'] ?? null);

        $body = self::compact([
            'message_id' => $messageId,
            'channel' => $channel,
            'account' => ['account_id' => $input['account_id']],
            'receiver' => [
                'phone_number' => $input['receiver']['phone_number'],
                'name' => $input['receiver']['name'],
            ],
            'content' => [
                'type' => $type,
                'data' => self::compact($input['content']['data'] ?? []),
            ],
        ]);

        $envelope = $this->http->request('POST', '/api/v1/messages', body: $body);

        return new Envelope(
            status: $envelope->status,
            message: $envelope->message,
            data: $envelope->data,
            metadata: ['message_id' => $messageId] + $envelope->metadata,
            raw: $envelope->raw,
            httpStatus: $envelope->httpStatus,
        );
    }

    public function list(): Envelope
    {
        return $this->http->request('GET', '/api/v1/messages');
    }

    public function get(string $messageId): Envelope
    {
        return $this->http->request('GET', '/api/v1/messages/' . rawurlencode($messageId));
    }

    private static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
