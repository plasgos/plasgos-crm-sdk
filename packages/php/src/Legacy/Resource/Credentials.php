<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Resource;

use Plasgos\CrmSdk\Legacy\Envelope;

/**
 * Credential-management endpoints (`/v2/integration/*`). Reached via
 * {@see \Plasgos\CrmSdk\CredentialsClient}.
 */
final class Credentials extends AbstractResource
{
    /**
     * Current API credential for the logged-in user (`data` is null if none).
     *
     * @return Envelope<mixed>
     */
    public function get(): Envelope
    {
        return $this->http->request('GET', '/v2/integration');
    }

    /**
     * Request API access — creates a pending credential awaiting admin approval.
     *
     * @return Envelope<mixed>
     */
    public function request(): Envelope
    {
        return $this->http->request('POST', '/v2/integration');
    }

    /**
     * Regenerate api_key + secret_key (only allowed when status is active).
     *
     * @return Envelope<mixed>
     */
    public function regenerate(): Envelope
    {
        return $this->http->request('POST', '/v2/integration/regenerate');
    }

    /**
     * @param array{page?: int, limit?: int} $params
     * @return Envelope<mixed>
     */
    public function messages(array $params = []): Envelope
    {
        return $this->http->request('GET', '/v2/integration/messages', query: self::query([
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]));
    }

    /**
     * Register (and server-side verify) the integrator webhook endpoint.
     *
     * @param array{webhook_url: string, verify_token?: string} $input
     * @return Envelope<mixed>
     */
    public function subscribeWebhook(array $input): Envelope
    {
        return $this->http->request('POST', '/v2/integration/webhook', body: self::compact([
            'webhook_url' => $input['webhook_url'],
            'verify_token' => $input['verify_token'] ?? null,
        ]));
    }
}
