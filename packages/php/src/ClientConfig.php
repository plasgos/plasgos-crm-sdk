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
        public readonly int $signatureTtl = 300,
        public readonly ?string $userAgent = null,
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?RequestFactoryInterface $requestFactory = null,
        public readonly ?StreamFactoryInterface $streamFactory = null,
    ) {}
}
