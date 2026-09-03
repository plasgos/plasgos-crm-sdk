<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * HMAC request signing, identical to the server:
 *
 *   signature = lowercase_hex( HMAC_SHA256( secretKey, "{apiKey}:{timestamp}" ) )
 *
 * The timestamp is Unix time in seconds. Method / path / body are NOT signed.
 */
final class Signature
{
    public static function compute(string $apiKey, string $secretKey, int $timestamp): string
    {
        return strtolower(hash_hmac('sha256', $apiKey . ':' . $timestamp, $secretKey));
    }

    public static function currentTimestamp(): int
    {
        return time();
    }

    /**
     * @return array{x-api-key: string, x-timestamp: string, x-signature: string}
     */
    public static function headers(string $apiKey, string $secretKey, ?int $timestamp = null): array
    {
        $timestamp ??= self::currentTimestamp();

        return [
            'x-api-key' => $apiKey,
            'x-timestamp' => (string) $timestamp,
            'x-signature' => self::compute($apiKey, $secretKey, $timestamp),
        ];
    }
}
