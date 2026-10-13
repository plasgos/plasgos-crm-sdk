<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Http;

/**
 * Reading decoded JSON (`json_decode(..., true)`) without trusting its shape.
 *
 * @internal
 */
final class Json
{
    /**
     * A JSON object, or null for anything else. Keys are strings in JSON; PHP stores numeric ones
     * such as "1" as ints, so the string-key type describes the JSON, not PHP's array.
     *
     * @return array<string, mixed>|null
     */
    public static function object(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }
        /** @var array<string, mixed> $value */
        return $value;
    }

    /** A string, a number as text, or `$default`. */
    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : (is_int($value) || is_float($value) ? (string) $value : $default);
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
