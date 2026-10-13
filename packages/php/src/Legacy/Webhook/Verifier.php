<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Webhook;

use Plasgos\CrmSdk\Exception\WebhookVerificationException;
use Plasgos\CrmSdk\Http\Json;
use Psr\Http\Message\ServerRequestInterface;

final class Verifier
{
    public const EVENT_MESSAGE_RECEIVED = 'message.received';
    public const EVENT_MESSAGE_STATUSES = 'message.statuses';

    /**
     * Verify an inbound webhook and return the decoded event array.
     *
     * Plasgos CRM does not HMAC-sign webhook payloads; it sends your
     * `verify_token` in the `x-hub-token` header. This compares that header with
     * `hash_equals()` and decodes the JSON body.
     *
     * @param array<array-key, string|array<string>> $headers  header name => value(s), e.g. PSR-7 `getHeaders()`
     * @return array<string, mixed>
     */
    public static function verify(array $headers, string $rawBody, ?string $expectedToken = null): array
    {
        if ($expectedToken !== null) {
            $received = self::header($headers, 'x-hub-token');
            if ($received === null || ! hash_equals($expectedToken, $received)) {
                throw new WebhookVerificationException('Webhook verification failed: x-hub-token mismatch');
            }
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebhookVerificationException('Webhook verification failed: body is not valid JSON', 0, $e);
        }

        $event = Json::object($decoded);
        if ($event === null) {
            throw new WebhookVerificationException('Webhook verification failed: body is not an object');
        }

        return $event;
    }

    /**
     * PSR-7 convenience wrapper.
     *
     * @return array<string, mixed>
     */
    public static function verifyRequest(ServerRequestInterface $request, ?string $expectedToken = null): array
    {
        return self::verify($request->getHeaders(), (string) $request->getBody(), $expectedToken);
    }

    /**
     * @param array<array-key, string|array<string>> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($value) ? (reset($value) ?: null) : $value;
            }
        }
        return null;
    }
}
