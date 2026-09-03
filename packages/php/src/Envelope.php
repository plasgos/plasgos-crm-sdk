<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * Normalised response wrapper returned by every successful SDK call.
 *
 * @template T
 */
final class Envelope
{
    /**
     * @param int|bool $status  status the body reported, else the real HTTP status
     * @param mixed    $data
     * @param array<string, mixed> $metadata
     * @param mixed    $raw  the untouched decoded body
     */
    public function __construct(
        public readonly int|bool $status,
        public readonly ?string $message,
        public readonly mixed $data,
        public readonly array $metadata,
        public readonly mixed $raw,
        public readonly int $httpStatus,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'httpStatus' => $this->httpStatus,
        ];
    }
}
