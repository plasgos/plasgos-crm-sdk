<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Webchat;

use Plasgos\CrmSdk\Http\ApiClient;

/**
 * Webchat identity token (docs/SDK_SPEC.md §8): tells the website chat widget who the signed-in customer is.
 *
 * Sign it on YOUR server with the widget's identity secret (`wcs_…`, shown once when the widget is created or the
 * secret is rotated) and hand only the token to the page:
 *
 * ```php
 * $token = Webchat::signIdentity(getenv('PLASGOS_WEBCHAT_IDENTITY_SECRET'), [
 *     'sub' => (string) $user->id, 'email' => $user->email, 'name' => $user->name,
 * ]);
 * // <script>Plasgos("boot", { widgetId: "wgt_…", identityToken: <?= json_encode($token) ?> });</script>
 * ```
 *
 * The secret must never reach the browser. JWT HS256 with claims `sub`, `email?`, `email_verified?`, `name?`, `phone?`,
 * `wid?`, `iat`, `exp`, `jti`. Each token is single-use per visitor, so sign a fresh one per page render / sign-in.
 */
final class Webchat
{
    public const IDENTITY_DEFAULT_TTL = 3600;
    public const IDENTITY_MAX_TTL = 86400;

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;

    /**
     * Signed identity token (JWT HS256) for `Plasgos("boot", { identityToken })` / `Plasgos("identify", { token })`.
     *
     * @param array<string, mixed> $identity `sub` (string|int, required) = stable id of the customer in your system
     *        (≤ 255 characters); `email`, `name`, `phone` (string|null, optional) fill empty fields of the CRM contact
     *        (`null`/`''` are left out). Checked at runtime — values often come from a database row.
     *        `email_verified` (bool, optional) = `true` ONLY when your site verified the customer owns `email`; only
     *        then is the email used to link the visitor to an existing CRM contact (omitted when not `true`).
     * @param string|null $widgetId binds the token to one widget (`wgt_…`)
     * @param int $ttl lifetime in seconds, 1–86400 (the server rejects longer)
     * @param int|null $now Unix seconds; for tests
     * @param string|null $jti unique token id (≤ 128 characters); a random UUID by default
     *
     * @throws \InvalidArgumentException
     */
    public static function signIdentity(
        string $secret,
        array $identity,
        ?string $widgetId = null,
        int $ttl = self::IDENTITY_DEFAULT_TTL,
        ?int $now = null,
        ?string $jti = null,
    ): string {
        if ($secret === '') {
            throw new \InvalidArgumentException('Webchat::signIdentity: secret is required');
        }
        $sub = $identity['sub'] ?? null;
        $sub = is_int($sub) ? (string) $sub : $sub;
        if (! is_string($sub) || trim($sub) === '' || self::length($sub) > 255) {
            throw new \InvalidArgumentException('Webchat::signIdentity: sub must be a non-empty string of at most 255 characters');
        }
        if ($ttl < 1 || $ttl > self::IDENTITY_MAX_TTL) {
            throw new \InvalidArgumentException('Webchat::signIdentity: ttl must be an integer between 1 and ' . self::IDENTITY_MAX_TTL . ' seconds');
        }
        if ($widgetId === '') {
            throw new \InvalidArgumentException('Webchat::signIdentity: widgetId must be a non-empty string');
        }
        $jti ??= ApiClient::uuid4();
        if ($jti === '' || self::length($jti) > 128) {
            throw new \InvalidArgumentException('Webchat::signIdentity: jti must be a non-empty string of at most 128 characters');
        }
        $iat = $now ?? time();

        // Fixed key order: tokens are byte-identical across the Node, PHP and Python SDKs (docs/vectors-v3.json).
        $verified = $identity['email_verified'] ?? null;
        if ($verified !== null && ! is_bool($verified)) {
            throw new \InvalidArgumentException('Webchat::signIdentity: email_verified must be a boolean');
        }
        $payload = ['sub' => $sub];
        foreach (['email', 'name', 'phone'] as $key) {
            $value = $identity[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_string($value)) {
                throw new \InvalidArgumentException("Webchat::signIdentity: {$key} must be a string");
            }
            $payload[$key] = $value;
            if ($key === 'email' && $verified === true) {
                $payload['email_verified'] = true;
            }
        }
        if ($widgetId !== null) {
            $payload['wid'] = $widgetId;
        }
        $payload['iat'] = $iat;
        $payload['exp'] = $iat + $ttl;
        $payload['jti'] = $jti;

        try {
            $signingInput = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], self::JSON_FLAGS))
                . '.' . self::base64url(json_encode($payload, self::JSON_FLAGS));
        } catch (\JsonException $error) {
            throw new \InvalidArgumentException('Webchat::signIdentity: identity is not valid UTF-8 text', 0, $error);
        }

        return $signingInput . '.' . self::base64url(hash_hmac('sha256', $signingInput, $secret, true));
    }

    /** Length in UTF-16 code units — the way the server (JavaScript) counts; invalid UTF-8 counts as too long. */
    private static function length(string $value): int
    {
        $codePoints = preg_match_all('/./su', $value);
        $astral = preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $value);
        return $codePoints === false || $astral === false ? PHP_INT_MAX : $codePoints + $astral;
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
