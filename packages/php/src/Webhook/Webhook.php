<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Webhook;

use Plasgos\CrmSdk\Exception\WebhookVerificationException;
use Plasgos\CrmSdk\Http\Json;
use Plasgos\CrmSdk\Signature;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API v3 webhook verification (docs/SDK_SPEC.md §7):
 *
 *   Plasgos-Webhook-Signature = "v1=" + hex(hmac_sha256(signing_secret, id + "." + timestamp + "." + raw_body))
 *
 * ```php
 * $event = Webhook::verify(file_get_contents('php://input'), getallheaders(), $signingSecret);
 * ```
 *
 * Deliveries may repeat — drop event ids you have already processed.
 */
final class Webhook
{
    public const DEFAULT_TOLERANCE = 300;

    /**
     * @param array<array-key, string|array<string>> $headers any casing; `HTTP_*` server keys or PSR-7 `getHeaders()`
     * @param string|list<string|null> $secrets current secret, or current + previous during rotation (null/empty skipped)
     * @return array<string, mixed> the decoded event
     */
    public static function verify(string $rawBody, array $headers, string|array $secrets, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): array
    {
        $headers = self::normalize($headers);
        $id = $headers['plasgos-webhook-id'] ?? '';
        $timestamp = $headers['plasgos-webhook-timestamp'] ?? '';
        $signature = $headers['plasgos-webhook-signature'] ?? '';

        if ($id === '' || $timestamp === '' || $signature === '') {
            throw new WebhookVerificationException('Webhook verification failed: signature headers missing');
        }
        if (! ctype_digit($timestamp)) {
            throw new WebhookVerificationException('Webhook verification failed: invalid timestamp');
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw new WebhookVerificationException('Webhook verification failed: timestamp outside tolerance');
        }

        $candidates = [];
        foreach (preg_split('/[\s,]+/', $signature) ?: [] as $part) {
            if (str_starts_with($part, Signature::PREFIX)) {
                $candidates[] = substr($part, strlen(Signature::PREFIX));
            }
        }
        if ($candidates === []) {
            throw new WebhookVerificationException('Webhook verification failed: unsupported signature version');
        }

        $payload = $id . '.' . $timestamp . '.' . $rawBody;
        $matched = false;
        foreach ((array) $secrets as $secret) {
            if (! is_string($secret) || $secret === '') {
                continue;
            }
            $expected = hash_hmac('sha256', $payload, $secret);
            foreach ($candidates as $candidate) {
                $matched = hash_equals($expected, $candidate) || $matched;
            }
        }
        if (! $matched) {
            throw new WebhookVerificationException('Webhook verification failed: signature mismatch');
        }

        try {
            $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebhookVerificationException('Webhook verification failed: body is not valid JSON', 0, $e);
        }
        $event = Json::object($event);
        if ($event === null || ! isset($event['id'], $event['type'])) {
            throw new WebhookVerificationException('Webhook verification failed: body is not an event');
        }
        if (Json::string($event['id']) !== $id) {
            throw new WebhookVerificationException('Webhook verification failed: event id does not match header');
        }

        return $event;
    }

    /**
     * PSR-7 convenience wrapper.
     *
     * @param string|list<string|null> $secrets
     * @return array<string, mixed>
     */
    public static function verifyRequest(ServerRequestInterface $request, string|array $secrets, int $tolerance = self::DEFAULT_TOLERANCE): array
    {
        return self::verify((string) $request->getBody(), $request->getHeaders(), $secrets, $tolerance);
    }

    /**
     * Headers the CRM would send — for tests and local tooling.
     *
     * @return array{Plasgos-Webhook-Id: string, Plasgos-Webhook-Timestamp: string, Plasgos-Webhook-Signature: string}
     */
    public static function signatureHeaders(string $id, int $timestamp, string $rawBody, string $secret): array
    {
        return [
            'Plasgos-Webhook-Id' => $id,
            'Plasgos-Webhook-Timestamp' => (string) $timestamp,
            'Plasgos-Webhook-Signature' => Signature::PREFIX . hash_hmac('sha256', $id . '.' . $timestamp . '.' . $rawBody, $secret),
        ];
    }

    /**
     * @param array<array-key, string|array<string>> $headers
     * @return array<string, string>
     */
    private static function normalize(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $key = strtolower(str_replace('_', '-', (string) $name));
            if (str_starts_with($key, 'http-')) {
                $key = substr($key, 5);
            }
            $out[$key] = trim(is_array($value) ? (reset($value) ?: '') : $value);
        }
        return $out;
    }
}
