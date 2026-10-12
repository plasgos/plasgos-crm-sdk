# plasgos/crm-sdk (PHP)

Official PHP SDK for **Plasgos CRM: API Integrasi v3**. The SDK covers:

- omnichannel messages (WhatsApp official and unofficial, Instagram, Messenger)
- contacts and leads
- broadcasts
- followups
- signed webhooks

Features:

- PHP 8.1+ with PSR-18 / PSR-17 transport (Guzzle by default, injectable).
- Automatic request signing that covers the method, path, query and body.
- An automatic `Idempotency-Key` on every POST, reused on retries.
- Typed exceptions carrying the API `errorCode`.
- A `Page` type with `autoPaging()`.
- Webhook HMAC verification.

> Upgrading from 1.x? See [UPGRADING.md](../../UPGRADING.md). The v1 client lives on as the
> deprecated `LegacyClient`.

## Install

```bash
composer require plasgos/crm-sdk
```

## Quick start

```php
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Content;
use Plasgos\CrmSdk\Enum\BaseUrl;
use Plasgos\CrmSdk\Exception\PlasgosApiException;

$client = new Client(
    getenv('PLASGOS_API_KEY'),
    getenv('PLASGOS_SECRET_KEY'),
    new ClientConfig(baseUrl: BaseUrl::Sandbox), // Production by default; or an absolute URL
);

$me = $client->me->get();
echo $me->data['tenant']['name'], PHP_EOL;

try {
    $sent = $client->messages->send([
        'endpoint_id' => '6281100000001',
        'recipient' => ['phone_number' => '081234567890'],
        'create_contact' => ['name' => 'Budi Santoso'],
        'content' => Content::template('66f1a2b3c4d5e6f708192a3b')
            ->dynamic('name', '{{full_name}}')
            ->custom('order', 'INV-2026-0917'),
    ], idempotencyKey: 'wc-1234-processing');
    echo $sent->data['status'], PHP_EOL; // pending|sent|failed (202)
} catch (PlasgosApiException $e) {
    // Branch on the stable code; show getMessage() (Indonesian) to people.
    if ($e->errorCode === 'requires_template') { /* ... */ }
    error_log("{$e->errorCode} {$e->requestId}");
}

foreach ($client->contacts->list(['per_page' => 100])->autoPaging() as $contact) {
    // every page fetched lazily
}

$client->contacts->upsert(['phone_number' => '081234567890', 'name' => 'Budi', 'tags' => ['woocommerce']]);
$client->leads->create(['contact' => ['phone_number' => '081234567890'], 'pipeline_id' => 'pl_1']);
$client->followups->enroll('fu_1', ['contact_id' => 'ct_1']);
```

Resources:

- `me`, `channels`
- `endpoints` (including `templates`), `templateVariables`
- `messages`
- `contacts`, `contactGroups`
- `pipelines`, `leads`
- `broadcasts`, `followups`
- `webhooks`

`$client->request($method, $path, ...)` is an escape hatch for endpoints that do not have a method
yet. The full surface is in [docs/SDK_SPEC.md](../../docs/SDK_SPEC.md) §3.

## Responses

```php
$r->httpStatus;  // 200, 201, 202 …
$r->message;     // user-facing text
$r->data;        // payload
$r->meta;        // e.g. ['operation' => 'created'] for upserts
$r->requestId;   // quote it when contacting Plasgos
$r->rateLimit;   // ['limit' => 120, 'remaining' => 119, 'reset' => 42]
$r->replayed;    // true when the server replayed the first response for this Idempotency-Key
```

List methods return a `Page`, which provides:

- `->items` and `->pagination`
- `->hasNextPage()` and `->nextPage()`
- `->autoPaging()`
- iteration and `count()` over the current page

## Errors

```
PlasgosApiException
├─ AuthenticationException            401
│  └─ SignatureExpiredException       401 signature_expired (check the server clock)
├─ PermissionException                403 (scope_missing, api_key_revoked, …)
│  └─ PlanException                   403 not_entitled
├─ NotFoundException                  404
├─ ConflictException                  409 (idempotency_in_progress, contact_exists, …)
├─ BadRequestException                400
├─ ValidationException                422 / 400 with fields  → ->fields
├─ RateLimitException                 429 → ->retryAfter (rate_limited | daily_quota_exceeded)
├─ ServerException                    5xx
│  ├─ ChannelException                502 provider error
│  └─ ServiceUnavailableException     503
└─ TransportException                 no response
```

Every exception exposes `errorCode`, `errorType`, `details`, `fields`, `docUrl`, `requestId` and
`retryAfter`.

**Retries.** Transport errors, 502/503 and `rate_limited` (for any method) are retried
automatically up to `maxRetries`, reusing the same idempotency key. A plain 500 is retried only for
GET. Other 4xx errors and `daily_quota_exceeded` are never retried.

## Webhooks

```php
use Plasgos\CrmSdk\Webhook\EventType;
use Plasgos\CrmSdk\Webhook\Webhook;

$event = Webhook::verify(file_get_contents('php://input'), getallheaders(), $signingSecret);
// during secret rotation: Webhook::verify($body, $headers, [$newSecret, $previousSecret]);
// PSR-7: Webhook::verifyRequest($serverRequest, $signingSecret);

if ($event['type'] === EventType::MESSAGE_RECEIVED) { /* $event['data'] is a Message */ }
```

Deliveries can repeat, so drop `$event['id']` values you have already processed.

## Custom transport

```php
new ClientConfig(
    httpClient: $psr18Client,          // e.g. an adapter over wp_remote_request()
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
    timeout: 20.0,
    maxRetries: 2,
    maxRetryWait: 30,
    userAgent: 'my-app/1.0 plasgos-crm-sdk-php/2.0.0',
);
```

## Legacy v1 client (deprecated)

`LegacyClient` (`/api/v1`) and `CredentialsClient` (`/v2/integration`) keep the 1.x behaviour and
the `Legacy\Envelope` response type. See [docs/SDK_SPEC_LEGACY_V1.md](../../docs/SDK_SPEC_LEGACY_V1.md).

## Development

```bash
composer install
composer test
# optional smoke test against a real server:
PLASGOS_LIVE_BASE_URL=https://api-crm.sandbox.plasgos.co.id PLASGOS_LIVE_API_KEY=… PLASGOS_LIVE_SECRET_KEY=… composer test -- --filter LiveTest
```
