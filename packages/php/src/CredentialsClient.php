<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

use Plasgos\CrmSdk\Http\Transport;
use Plasgos\CrmSdk\Legacy\Resource\Credentials;

/**
 * Client for the credential-management endpoints (`/v2/integration/*`).
 *
 * These use the CRM user-session auth, not signed API auth:
 * `Authorization: Bearer <accessToken>` + `fingerprint: <fingerprint>`.
 * Pass the exact token string your CRM session issued — the SDK does not
 * encrypt or refresh it. Responses use the v1-style {@see Legacy\Envelope}.
 *
 * ```php
 * $creds = new \Plasgos\CrmSdk\CredentialsClient($accessToken, $fingerprint);
 * $creds->credentials->request();
 * $current = $creds->credentials->get();
 * ```
 */
final class CredentialsClient
{
    public readonly Credentials $credentials;

    public function __construct(
        private readonly string $accessToken,
        private readonly string $fingerprint,
        ?ClientConfig $config = null,
    ) {
        if ($accessToken === '') {
            throw new \InvalidArgumentException('CredentialsClient: accessToken is required');
        }
        if ($fingerprint === '') {
            throw new \InvalidArgumentException('CredentialsClient: fingerprint is required');
        }

        $config ??= new ClientConfig();

        $http = Transport::legacy($config, fn (): array => [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'fingerprint' => $this->fingerprint,
        ]);

        $this->credentials = new Credentials($http);
    }
}
