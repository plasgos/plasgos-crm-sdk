# plasgos/crm-sdk (PHP)

Official PHP SDK for the **Plasgos CRM — Api Integration** API.

- PHP 8.1+, PSR-18 / PSR-17 transport (Guzzle by default, injectable).
- Automatic HMAC request signing.
- Normalised `Envelope`, typed exceptions.
- Webhook verification helper.
- Separate client for the credential-management endpoints.

## Install

```bash
composer require plasgos/crm-sdk
```

## Quick start (signed API)

```php
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Enum\BaseUrl;
use Plasgos\CrmSdk\Enum\Channel;

$client = new Client(
    getenv('PLASGOS_API_KEY'),
    getenv('PLASGOS_SECRET_KEY'),
    new ClientConfig(baseUrl: BaseUrl::Production), // or BaseUrl::Sandbox / a URL string
);

$env = $client->messages->send([
    'channel' => Channel::WhatsApp,
    'account_id' => '665f1c2e9a1b2c3d4e5f6a7b',
    'receiver' => ['phone_number' => '628123456789', 'name' => 'John Doe'],
    'content' => ['type' => 'text', 'data' => ['text' => 'Halo dari SDK']],
]);
echo $env->metadata['message_id'], PHP_EOL;

$accounts = $client->accounts->list();

$client->official->messages->sendTemplate([
    'phone_number_id' => '123456789012345',
    'to' => '6281234567890',
    'template' => [
        'id' => '1234567890123456',
        'name' => 'order_update_v1',
        'category' => 'UTILITY',
        'parameters' => ['variables' => [['type' => 'custom', 'name' => '1', 'value' => 'Budi']]],
    ],
]);
```

Every call returns an `Envelope`:

```php
final class Envelope {
    public readonly int|bool $status;
    public readonly ?string $message;
    public readonly mixed $data;
    public readonly array $metadata;
    public readonly mixed $raw;
    public readonly int $httpStatus;
}
```

## Errors

```php
use Plasgos\CrmSdk\Exception\ValidationException;
use Plasgos\CrmSdk\Exception\RateLimitException;

try {
    $client->messages->send([/* ... */]);
} catch (ValidationException $e) {
    var_dump($e->errors);
} catch (RateLimitException $e) {
    // back off
}
```

Hierarchy: `PlasgosApiException` → `AuthenticationException`,
`PermissionException` (`PlanException`), `NotFoundException`,
`SignatureExpiredException`, `ConflictException`, `ValidationException`,
`BadRequestException`, `RateLimitException`, `ServerException`,
`TransportException`.

> An un-approved API key can surface as HTTP 500 (`ServerException`) with a
> message containing `"You don't have permission"` — a known server quirk.

## Credential management (`/v2/integration/*`)

```php
use Plasgos\CrmSdk\CredentialsClient;

$creds = new CredentialsClient($accessToken, $fingerprint);
$creds->credentials->request();
$current = $creds->credentials->get();
$creds->credentials->regenerate();
$creds->credentials->subscribeWebhook([
    'webhook_url' => 'https://your.app/webhooks/plasgos',
    'verify_token' => 'a-shared-secret',
]);
```

## Webhooks

```php
use Plasgos\CrmSdk\Webhook\Verifier;

$event = Verifier::verify(getallheaders(), file_get_contents('php://input'), 'a-shared-secret');
// or: Verifier::verifyRequest($psr7ServerRequest, 'a-shared-secret');

if ($event['event'] === Verifier::EVENT_MESSAGE_RECEIVED) {
    // ...
}
```

`Verifier` compares the `x-hub-token` header with `hash_equals()` and decodes
the JSON body. There is no payload HMAC.

## Custom transport

```php
new ClientConfig(
    httpClient: $psr18Client,          // any Psr\Http\Client\ClientInterface
    requestFactory: $psr17RequestFactory,
    streamFactory: $psr17StreamFactory,
    timeout: 20.0,
    maxRetries: 3,
);
```

## Development

```bash
composer install
composer test
```
