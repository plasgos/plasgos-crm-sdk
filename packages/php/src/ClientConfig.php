<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

use Plasgos\CrmSdk\Enum\BaseUrl;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Optional configuration for {@see Client} / {@see CredentialsClient}.
 *
 * All transport pieces are injectable so the SDK stays framework-agnostic; when
 * omitted, Guzzle is used (it ships PSR-18 + PSR-17 implementations).
 */
final class ClientConfig
{
    public function __construct(
        public readonly BaseUrl|string|null $baseUrl = null,
        public readonly float $timeout = 15.0,
        public readonly int $maxRetries = 2,
        /** {@see LegacyClient} (v1) only — informational server TTL in seconds; the v3 {@see Client} ignores it. */
        public readonly int $signatureTtl = 300,
        public readonly ?string $userAgent = null,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?RequestFactoryInterface $requestFactory = null,
        public readonly ?StreamFactoryInterface $streamFactory = null,
        /** v3: longest `Retry-After` (seconds) the client waits automatically; longer ones are thrown. */
        public readonly int $maxRetryWait = 30,
        /** Testing hook: fn(float $seconds): void — defaults to usleep. */
        public readonly ?\Closure $sleeper = null,
        /** Testing hook: fn(): int unix seconds — defaults to time(). */
        public readonly ?\Closure $clock = null,
    ) {}
}
