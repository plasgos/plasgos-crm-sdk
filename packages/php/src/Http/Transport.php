<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Enum\BaseUrl;
use Plasgos\CrmSdk\Legacy\Http\HttpClient as LegacyHttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds the PSR-18/PSR-17 pieces from a {@see ClientConfig}, defaulting to Guzzle.
 * Shared by the v3 {@see \Plasgos\CrmSdk\Client} and the legacy clients.
 */
final class Transport
{
    public const VERSION = '2.0.0';

    /**
     * @return array{client: ClientInterface, requestFactory: RequestFactoryInterface, streamFactory: StreamFactoryInterface, baseUrl: string, userAgent: string}
     */
    public static function psr(ClientConfig $config): array
    {
        $client = $config->httpClient ?? new GuzzleClient([
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => $config->timeout,
            'connect_timeout' => $config->timeout,
        ]);
        $psr17 = new HttpFactory();

        return [
            'client' => $client,
            'requestFactory' => $config->requestFactory ?? $psr17,
            'streamFactory' => $config->streamFactory ?? $psr17,
            'baseUrl' => BaseUrl::resolve($config->baseUrl),
            'userAgent' => $config->userAgent ?? 'plasgos-crm-sdk-php/' . self::VERSION . ' php/' . PHP_VERSION,
        ];
    }

    /**
     * HTTP client for the legacy v1-style endpoints (`/api/v1/*`, `/v2/integration/*`).
     *
     * @param callable(): array<string, string> $authHeaders
     */
    public static function legacy(ClientConfig $config, callable $authHeaders): LegacyHttpClient
    {
        $psr = self::psr($config);

        return new LegacyHttpClient(
            client: $psr['client'],
            requestFactory: $psr['requestFactory'],
            streamFactory: $psr['streamFactory'],
            baseUrl: $psr['baseUrl'],
            userAgent: $psr['userAgent'],
            maxRetries: $config->maxRetries,
            authHeaders: $authHeaders,
        );
    }
}
