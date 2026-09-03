<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Enum\BaseUrl;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds an {@see HttpClient} from a {@see ClientConfig}, defaulting to Guzzle.
 */
final class Transport
{
    public const VERSION = '1.0.0';

    /**
     * @param callable(): array<string, string> $authHeaders
     */
    public static function fromConfig(ClientConfig $config, callable $authHeaders): HttpClient
    {
        $guzzleFallback = null;
        $needGuzzle = $config->httpClient === null
            || $config->requestFactory === null
            || $config->streamFactory === null;

        if ($needGuzzle) {
            $guzzleFallback = new GuzzleClient([
                'http_errors' => false,
                'timeout' => $config->timeout,
                'connect_timeout' => $config->timeout,
            ]);
        }

        $psr17 = ($config->requestFactory === null || $config->streamFactory === null)
            ? new HttpFactory()
            : null;

        /** @var ClientInterface $client */
        $client = $config->httpClient ?? $guzzleFallback;
        /** @var RequestFactoryInterface $requestFactory */
        $requestFactory = $config->requestFactory ?? $psr17;
        /** @var StreamFactoryInterface $streamFactory */
        $streamFactory = $config->streamFactory ?? $psr17;

        return new HttpClient(
            client: $client,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            baseUrl: BaseUrl::resolve($config->baseUrl),
            userAgent: $config->userAgent ?? 'plasgos-crm-sdk-php/' . self::VERSION,
            maxRetries: $config->maxRetries,
            authHeaders: $authHeaders,
        );
    }
}
