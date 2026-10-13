<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * Per-call overrides (docs/SDK_SPEC.md §1.1); every resource method takes one as its last argument.
 *
 * ```php
 * $client->contacts->get($id, new RequestOptions(timeout: 5, maxRetries: 0, headers: ['X-Trace' => $traceId]));
 * ```
 */
final class RequestOptions
{
    /** Headers the SDK owns: overriding them would break signing or idempotency. */
    private const RESERVED_HEADERS = ['accept', 'user-agent', 'plasgos-api-key', 'plasgos-timestamp', 'plasgos-signature', 'idempotency-key', 'content-type', 'content-length', 'host'];

    /**
     * @param float|null $timeout seconds for each attempt of this call; needs the default (Guzzle) transport
     * @param int|null $maxRetries automatic retries for this call (§6)
     * @param array<string, string> $headers extra headers, e.g. tracing; signing, `Idempotency-Key`,
     *                                       `Content-Type`, `Accept` and `User-Agent` stay with the SDK
     */
    public function __construct(
        public readonly ?float $timeout = null,
        public readonly ?int $maxRetries = null,
        public readonly array $headers = [],
    ) {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), self::RESERVED_HEADERS, true)) {
                throw new \InvalidArgumentException("RequestOptions headers: \"{$name}\" is set by the SDK");
            }
        }
    }
}
