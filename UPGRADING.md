# Upgrading

## 1.x → 2.0 (PHP)

SDK 2.0 targets **API Integrasi v3** (`/api/v3`): omnichannel messages, contacts, leads, broadcasts,
followups and HMAC-signed webhooks. The `/api/v1` client is kept as a deprecated `LegacyClient`
(removed in 3.0), so you can upgrade the package first and migrate call sites at your own pace.

### Step 1: keep working on v1 (rename only)

| 1.x | 2.0 |
|---|---|
| `Plasgos\CrmSdk\Client` | `Plasgos\CrmSdk\LegacyClient` |
| `Plasgos\CrmSdk\Envelope` | `Plasgos\CrmSdk\Legacy\Envelope` |
| `Plasgos\CrmSdk\Signature` | `Plasgos\CrmSdk\Legacy\Signature` |
| `Plasgos\CrmSdk\Webhook\Verifier` | `Plasgos\CrmSdk\Legacy\Webhook\Verifier` |
| `Plasgos\CrmSdk\Enum\Channel` / `ContentType` (v1 values) | `Plasgos\CrmSdk\Legacy\Enum\Channel` / `ContentType` |
| `Plasgos\CrmSdk\Resource\*`, `Official` | `Plasgos\CrmSdk\Legacy\Resource\*`, `Legacy\Official` |

Unchanged:

- `ClientConfig`, `CredentialsClient` and `Enum\BaseUrl`.
- The exception class names. `SignatureExpiredException` now also extends `AuthenticationException`.
  `ChannelException` and `ServiceUnavailableException` (both `ServerException`) are new.

### Step 2: move to v3

| v1 (`LegacyClient`) | v3 (`Client`) |
|---|---|
| `messages->send(['channel', 'account_id', 'receiver', 'content' => ['type','data']])` | `messages->send(['endpoint_id', 'recipient' => ['phone_number' \| 'contact_id' \| 'identity'], 'content' => Content::text(...)], idempotencyKey: ...)`; any channel |
| `official->messages->sendTemplate([...])` | `messages->send([... 'content' => Content::template($id)->dynamic(...)->custom(...)])` |
| `accounts->list()`, `official->accounts->list()` | `endpoints->list()`, covering every channel and transport |
| `official->templates->list($accountId)` | `endpoints->templates($endpointId)` |
| `references->variables()` | `templateVariables->list()` |
| `references->contacts()` | `contacts->list()`, plus `lookup`, `create`, `upsert`, `update` |
| `official->broadcasts->create/list/monitoring` | `broadcasts->create/list/get/recipients/cancel` (official and unofficial) |
| — | `leads`, `pipelines`, `followups` (enrol/unenrol), `webhooks`, `me` |
| `Envelope` (`status`, `message`, `data`, `metadata`) | `Response` (`httpStatus`, `message`, `data`, `meta`, `requestId`, `rateLimit`, `replayed`); lists return `Page` with `autoPaging()` |
| `$e->errors` | `$e->errorCode` (branch on it), `$e->fields`, `$e->details`, `$e->retryAfter` |
| `Webhook\Verifier` (`x-hub-token`) | `Webhook\Webhook::verify($rawBody, $headers, $signingSecret)` (HMAC `Plasgos-Webhook-Signature`) |

The same API key and secret work on v3; only the signing scheme differs, and the SDK handles it.
Capabilities without a v1 equivalent (contacts, leads, followups, webhooks) need the matching scopes
on the key. Ask Plasgos to grant them.

## Node

`plasgos-crm-sdk` 2.0 implements the same contract ([docs/SDK_SPEC.md](docs/SDK_SPEC.md)).

| 1.x | 2.0 |
|---|---|
| `new PlasgosCrmClient(key, secret, opts)` (`/api/v1`) | `new Client(key, secret, opts)` (`/api/v3`); the old class is `LegacyClient` (`PlasgosCrmClient` stays as a deprecated alias) |
| `client.messages.send({ channel, account, receiver, content })` | `client.messages.send({ endpoint_id, recipient, content }, idempotencyKey)` |
| `client.official.broadcasts.create(...)` | `client.broadcasts.create({ name, endpoint_id, content, audience })` |
| Envelope `{ status, message, data, metadata }` | `ApiResponse { httpStatus, message, data, meta, requestId, rateLimit, replayed }`, list methods return `Page` |
| `verifyWebhook(headers, body, { expectedToken })` (`x-hub-token`) | `Webhook.verify(rawBody, headers, signingSecret)` (HMAC `Plasgos-Webhook-Signature`) |
| `SignatureExpiredError` (408) | still exported; now also an `AuthenticationError` (v3: 401 `signature_expired`) |

## Python

`plasgos-crm-sdk` 2.0 implements the same contract ([docs/SDK_SPEC.md](docs/SDK_SPEC.md)); methods
are `snake_case`.

| 1.x | 2.0 |
|---|---|
| `PlasgosCrmClient(key, secret, **opts)` (`/api/v1`) | `Client(key, secret, **opts)` (`/api/v3`); the old class is `LegacyClient` (`PlasgosCrmClient` stays as a deprecated alias and emits a `DeprecationWarning`) |
| `client.messages.send(channel=..., account_id=..., receiver=..., content=...)` | `client.messages.send({"endpoint_id": ..., "recipient": ..., "content": ...}, idempotency_key=...)` |
| `client.official.broadcasts.create(...)` | `client.broadcasts.create({"name": ..., "endpoint_id": ..., "content": ..., "audience": ...})` |
| `Envelope(status, message, data, metadata)` | `ApiResponse(http_status, message, data, meta, request_id, rate_limit, replayed)`, list methods return `Page` |
| `verify_webhook(headers, body, expected_token=...)` (`x-hub-token`) | `Webhook.verify(raw_body, headers, signing_secret)` (HMAC `Plasgos-Webhook-Signature`) |
| `SignatureExpiredError` (408) | still exported; now also an `AuthenticationError` (v3: 401 `signature_expired`) |
