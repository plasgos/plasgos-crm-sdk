<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * API v3 request signing (docs/SDK_SPEC.md §2):
 *
 *   string_to_sign = timestamp + "\n" + METHOD + "\n" + request_target + "\n" + hex(sha256(raw_body))
 *   signature      = "v1=" + hex(hmac_sha256(secretKey, string_to_sign))
 *
 * `request_target` is the path + query exactly as sent (e.g. `/api/v3/contacts?page=2`).
 * Unlike the legacy scheme, method, path, query and body are all covered.
 */
final class Signature
{
    public const PREFIX = 'v1=';

    public static function stringToSign(int $timestamp, string $method, string $requestTarget, string $rawBody): string
    {
        return $timestamp . "\n" . strtoupper($method) . "\n" . $requestTarget . "\n" . hash('sha256', $rawBody);
    }

    public static function sign(string $secretKey, int $timestamp, string $method, string $requestTarget, string $rawBody): string
    {
        return self::PREFIX . hash_hmac('sha256', self::stringToSign($timestamp, $method, $requestTarget, $rawBody), $secretKey);
    }

    /**
     * @return array{Plasgos-Api-Key: string, Plasgos-Timestamp: string, Plasgos-Signature: string}
     */
    public static function headers(string $apiKey, string $secretKey, string $method, string $requestTarget, string $rawBody = '', ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            'Plasgos-Api-Key' => $apiKey,
            'Plasgos-Timestamp' => (string) $timestamp,
            'Plasgos-Signature' => self::sign($secretKey, $timestamp, $method, $requestTarget, $rawBody),
        ];
    }
}
