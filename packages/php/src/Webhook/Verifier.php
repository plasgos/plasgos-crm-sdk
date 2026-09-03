<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Webhook;

use Plasgos\CrmSdk\Exception\WebhookVerificationException;
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
     * @param array<string, string|list<string>> $headers  header name => value(s)
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

        if (! is_array($decoded)) {
            throw new WebhookVerificationException('Webhook verification failed: body is not an object');
        }

        return $decoded;
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
     * @param array<string, string|list<string>> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }
        return null;
    }
}
