<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

use Plasgos\CrmSdk\Http\ApiClient;
use Plasgos\CrmSdk\Http\Transport;
use Plasgos\CrmSdk\Resource\Broadcasts;
use Plasgos\CrmSdk\Resource\Channels;
use Plasgos\CrmSdk\Resource\ContactGroups;
use Plasgos\CrmSdk\Resource\Contacts;
use Plasgos\CrmSdk\Resource\Endpoints;
use Plasgos\CrmSdk\Resource\Followups;
use Plasgos\CrmSdk\Resource\Leads;
use Plasgos\CrmSdk\Resource\Me;
use Plasgos\CrmSdk\Resource\Messages;
use Plasgos\CrmSdk\Resource\Pipelines;
use Plasgos\CrmSdk\Resource\TemplateVariables;
use Plasgos\CrmSdk\Resource\Webhooks;

/**
 * Plasgos CRM API Integrasi v3 client (`/api/v3/*`).
 *
 * ```php
 * $client = new \Plasgos\CrmSdk\Client($apiKey, $secretKey, new \Plasgos\CrmSdk\ClientConfig(
 *     baseUrl: \Plasgos\CrmSdk\Enum\BaseUrl::Sandbox,
 * ));
 *
 * $client->messages->send([
 *     'endpoint_id' => '6281100000001',
 *     'contact' => ['phone_number' => '081234567890'],
 *     'content' => \Plasgos\CrmSdk\Content::template('66f1…')->dynamic('name', '{{full_name}}'),
 * ], idempotencyKey: 'wc-1234-processing');
 * ```
 *
 * Every request is signed (method, path, query and body), POSTs carry an Idempotency-Key that is
 * reused across retries, and errors are typed exceptions carrying `errorCode`.
 */
final class Client
{
    public readonly Me $me;
    public readonly Channels $channels;
    public readonly Endpoints $endpoints;
    public readonly TemplateVariables $templateVariables;
    public readonly Messages $messages;
    public readonly Contacts $contacts;
    public readonly ContactGroups $contactGroups;
    public readonly Pipelines $pipelines;
    public readonly Leads $leads;
    public readonly Broadcasts $broadcasts;
    public readonly Followups $followups;
    public readonly Webhooks $webhooks;

    private readonly ApiClient $http;

    public function __construct(string $apiKey, string $secretKey, ?ClientConfig $config = null)
    {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('Client: apiKey is required');
        }
        if (trim($secretKey) === '') {
            throw new \InvalidArgumentException('Client: secretKey is required');
        }

        $config ??= new ClientConfig();
        $psr = Transport::psr($config);
        $this->http = new ApiClient(
            client: $psr['client'],
            requestFactory: $psr['requestFactory'],
            streamFactory: $psr['streamFactory'],
            baseUrl: $psr['baseUrl'],
            userAgent: $psr['userAgent'],
            apiKey: trim($apiKey),
            secretKey: trim($secretKey),
            maxRetries: max(0, $config->maxRetries),
            maxRetryWait: max(0, $config->maxRetryWait),
            sleeper: $config->sleeper ?? static function (float $seconds): void {
                usleep((int) round($seconds * 1_000_000));
            },
            clock: $config->clock ?? static fn (): int => time(),
            logger: $config->logger,
        );

        $this->me = new Me($this->http);
        $this->channels = new Channels($this->http);
        $this->endpoints = new Endpoints($this->http);
        $this->templateVariables = new TemplateVariables($this->http);
        $this->messages = new Messages($this->http);
        $this->contacts = new Contacts($this->http);
        $this->contactGroups = new ContactGroups($this->http);
        $this->pipelines = new Pipelines($this->http);
        $this->leads = new Leads($this->http);
        $this->broadcasts = new Broadcasts($this->http);
        $this->followups = new Followups($this->http);
        $this->webhooks = new Webhooks($this->http);
    }

    /**
     * Raw call for endpoints that have no resource method yet.
     *
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|\JsonSerializable|\stdClass|null $body
     */
    public function request(string $method, string $path, array $query = [], array|\JsonSerializable|\stdClass|null $body = null, ?string $idempotencyKey = null, ?RequestOptions $options = null): Response
    {
        return $this->http->request($method, $path, $query, $body, $idempotencyKey, $options);
    }
}
