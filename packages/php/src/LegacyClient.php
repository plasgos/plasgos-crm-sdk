<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

use Plasgos\CrmSdk\Http\Transport;
use Plasgos\CrmSdk\Legacy\Official;
use Plasgos\CrmSdk\Legacy\Signature;
use Plasgos\CrmSdk\Legacy\Resource\Accounts;
use Plasgos\CrmSdk\Legacy\Resource\Messages;
use Plasgos\CrmSdk\Legacy\Resource\References;

/**
 * Signed API client for the LEGACY `/api/v1/*` API (WhatsApp only).
 *
 * @deprecated since 2.0 — use {@see Client} (API Integrasi v3: omnichannel, contacts, leads,
 *             broadcasts, followups, signed webhooks). Removed in 3.0. See UPGRADING.md.
 *
 * ```php
 * $client = new \Plasgos\CrmSdk\LegacyClient($apiKey, $secretKey, new \Plasgos\CrmSdk\ClientConfig(
 *     baseUrl: \Plasgos\CrmSdk\Enum\BaseUrl::Sandbox,
 * ));
 *
 * $env = $client->messages->send([
 *     'channel' => \Plasgos\CrmSdk\Legacy\Enum\Channel::WhatsApp,
 *     'account_id' => '665f1c2e9a1b2c3d4e5f6a7b',
 *     'receiver' => ['phone_number' => '628123456789', 'name' => 'John'],
 *     'content' => ['type' => 'text', 'data' => ['text' => 'Halo']],
 * ]);
 * ```
 */
final class LegacyClient
{
    public readonly Messages $messages;
    public readonly Accounts $accounts;
    public readonly References $references;
    public readonly Official $official;

    public readonly int $signatureTtl;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        ?ClientConfig $config = null,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('LegacyClient: apiKey is required');
        }
        if ($secretKey === '') {
            throw new \InvalidArgumentException('LegacyClient: secretKey is required');
        }

        $config ??= new ClientConfig();
        $this->signatureTtl = $config->signatureTtl;

        $http = Transport::legacy(
            $config,
            fn (): array => Signature::headers($this->apiKey, $this->secretKey),
        );

        $this->messages = new Messages($http);
        $this->accounts = new Accounts($http);
        $this->references = new References($http);
        $this->official = new Official($http);
    }

    /**
     * The three auth headers the SDK would send right now.
     *
     * @return array{x-api-key: string, x-timestamp: string, x-signature: string}
     */
    public function signatureHeaders(?int $timestamp = null): array
    {
        return Signature::headers($this->apiKey, $this->secretKey, $timestamp);
    }
}
