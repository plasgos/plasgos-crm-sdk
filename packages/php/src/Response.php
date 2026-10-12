<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * Successful API v3 response: `{ success: true, message, data, meta }`.
 */
final class Response
{
    /**
     * @param array<string, mixed> $meta
     * @param array{limit: ?int, remaining: ?int, reset: ?int} $rateLimit
     * @param array<string, string> $headers lower-cased header name => value
     */
    public function __construct(
        public readonly int $httpStatus,
        /** User-facing text (Indonesian). */
        public readonly string $message,
        public readonly mixed $data,
        public readonly array $meta,
        public readonly ?string $requestId,
        public readonly array $rateLimit,
        /** True when the server replayed the first response for the same Idempotency-Key. */
        public readonly bool $replayed,
        public readonly array $headers = [],
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
