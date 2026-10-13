<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * Successful API v3 response: `{ success: true, message, data, meta }`.
 *
 * Resource methods name the types of `data` and `meta` per operation, generated from OpenAPI
 * ({@see \Plasgos\CrmSdk\Types\ApiTypes}), e.g. `Response<GetContactData, GetContactMeta>`.
 *
 * @template-covariant TData
 * @template-covariant TMeta of array<string, mixed> = array<string, mixed>
 */
final class Response
{
    /**
     * @param TData $data
     * @param TMeta $meta
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
