# SDK spec — LEGACY `/api/v1` contract (deprecated)

> Superseded by [SDK_SPEC.md](SDK_SPEC.md) (API v3). Kept for `LegacyClient` (SDK 2.x) and SDK 1.x;
> removed together with `LegacyClient` in 3.0.

This document is the single source of truth for the **method surface**, the
**field mapping**, and the **error mapping** that the Node, PHP and Python SDKs
must implement identically. If the API changes, update
[`../openapi/openapi.yaml`](../openapi/openapi.yaml) (legacy v1) first, then this file, then
the three implementations.

## 1. Client construction

```
Client(apiKey, secretKey, options)
```

| Option          | Default                | Meaning                                                       |
|-----------------|------------------------|--------------------------------------------------------------|
| `baseUrl`       | `production`           | `production`, `sandbox`, or an absolute URL string.          |
| `timeout`       | `15000` ms             | Per-request timeout.                                          |
| `signatureTtl`  | `300` s                | Informational; used to warn if the local clock looks skewed. |
| `userAgent`     | `plasgos-crm-sdk-<lang>/<version>` | Sent as `User-Agent`.                           |
| `maxRetries`    | `2`                    | Retries for **idempotent** requests (GET) on network / 5xx / 429. |
| `fetch`/`httpClient` | platform default  | Injectable transport for tests / custom runtimes.            |

`production` → `https://wa-client.plasgos.co.id`
`sandbox`    → `https://api-crm.sandbox.plasgos.co.id`

### Credential-management client

`/v2/integration/*` uses a different auth. Expose it as a separate constructor so
the two auth modes never mix:

```
CredentialsClient(accessToken, fingerprint, options)   # options: baseUrl, timeout, userAgent, maxRetries
```

It sends `Authorization: Bearer <accessToken>` and `fingerprint: <fingerprint>`.
The SDK does not encrypt the token — the caller passes the exact token string
their CRM session issued.

## 2. Signing

For every `/api/v1/*` request:

```
timestamp = floor(now_ms / 1000)
signature = lowercase_hex( HMAC_SHA256( secretKey, apiKey + ":" + timestamp ) )
headers:
  x-api-key   = apiKey
  x-timestamp = str(timestamp)
  x-signature = signature
```

Recompute per request. Never cache the signature.

## 3. Resource methods

SDK method names are `camelCase` (Node), `camelCase` (PHP), `snake_case`
(Python). Wire field names are always `snake_case` (see column 3).

### `messages` (signed)

| Method | HTTP | Wire in / out |
|---|---|---|
| `messages.send(input)` | `POST /api/v1/messages` | `SendMessageRequest` → `Message` |
| `messages.list()` | `GET /api/v1/messages` | → `Message[]` |
| `messages.get(messageId)` | `GET /api/v1/messages/{message_id}` | → `Message` |

`SendMessageRequest` mapping (SDK camel → wire snake):

| SDK | Wire |
|---|---|
| `messageId` | `message_id` |
| `channel` | `channel` (`whatsapp` \| `telegram` \| `messanger` \| `instagram`) |
| `account.accountId` | `account.account_id` |
| `receiver.phoneNumber` | `receiver.phone_number` |
| `receiver.name` | `receiver.name` |
| `content.type` | `content.type` (`text` \| `image` \| `video` \| `document`) |
| `content.data.text` | `content.data.text` |
| `content.data.url` | `content.data.url` |
| `content.data.caption` | `content.data.caption` |
| `content.data.mimeType` | `content.data.mime_type` |
| `content.data.fileName` | `content.data.file_name` |

If `messageId` is omitted, the SDK generates a UUIDv4 and returns it on the
result so the caller can correlate.

### `accounts` (signed)

| Method | HTTP |
|---|---|
| `accounts.list()` | `GET /api/v1/accounts` |
| `accounts.get(accountId)` | `GET /api/v1/accounts/{account_id}` |

### `references` (signed)

| Method | HTTP |
|---|---|
| `references.variables()` | `GET /api/v1/references/variables` (note: `{success,data}` envelope) |
| `references.contacts({page?, limit?, search?})` | `GET /api/v1/references/contacts` |

### `official.accounts` (signed)

| Method | HTTP |
|---|---|
| `official.accounts.list()` | `GET /api/v1/official/accounts` |
| `official.accounts.phoneNumbers(accountId)` | `GET /api/v1/official/accounts/phone-numbers/{accountId}` |

### `official.templates` (signed)

| Method | HTTP |
|---|---|
| `official.templates.list(accountId, {category?})` | `GET /api/v1/official/templates/{accountId}` |

### `official.messages` (signed)

| Method | HTTP |
|---|---|
| `official.messages.sendTemplate(input)` | `POST /api/v1/official/messages` |

`input` maps `phoneNumberId` → `phone_number_id`; `template` is passed through
mostly as-is (`id`, `name`, `category`, `parameters.variables[]`,
`parameters.attachment`). `attachment.fileName` → `attachment.filename`,
`attachment.originalName` → `attachment.original_name`,
`attachment.fileSize` → `attachment.filesize`.

### `official.broadcasts` (signed)

| Method | HTTP |
|---|---|
| `official.broadcasts.create(input)` | `POST /api/v1/official/broadcasts` (returns `201`) |
| `official.broadcasts.list({page?, limit?, status?, search?})` | `GET /api/v1/official/broadcasts` |
| `official.broadcasts.monitoring(broadcastId)` | `GET /api/v1/official/broadcasts/{broadcastId}/monitoring` |

`input`: `accountId`→`account_id`, `templateId`→`template_id`,
`phoneNumberId`→`phone_number_id`, `scheduledAt`→`scheduled_at`,
`requestId`→`request_id`, `recipients[].phoneNumber`→`recipients[].phone_number`,
`recipients[].attachment.fileName`→`recipients[].attachment.file_name`.

### `official.sharedWaba` (signed)

| Method | HTTP |
|---|---|
| `official.sharedWaba.sendOtp(serialToken, {to, otpCode?, metadata?})` | `POST /api/v1/official/shared-waba/otp/{serial_token}` |

### `credentials` (Bearer) — `CredentialsClient`

| Method | HTTP |
|---|---|
| `credentials.get()` | `GET /v2/integration` |
| `credentials.request()` | `POST /v2/integration` |
| `credentials.regenerate()` | `POST /v2/integration/regenerate` |
| `credentials.messages({page?, limit?})` | `GET /v2/integration/messages` |
| `credentials.subscribeWebhook({webhookUrl, verifyToken?})` | `POST /v2/integration/webhook` |

`webhookUrl` → `webhook_url`, `verifyToken` → `verify_token`.

## 4. Response normalisation

Every successful call returns a normalised envelope:

```
{
  status:   number,          # HTTP-ish status the body reported, else the real HTTP status
  message:  string | null,
  data:     any,             # `data` field, or for {success,data} bodies, that data
  metadata: object,          # `metadata` OR `meta` OR {}
  raw:      object            # the untouched parsed body
  httpStatus: number          # the real HTTP status
}
```

Rules:
- `{success:true,data}` → `status = httpStatus`, `message = null`, `data = body.data`.
- `{status,message,data,metadata}` → copied through.
- `{status,message,data,meta}` (v2) → `metadata = body.meta`.
- Body is not an object (empty / text) → `data = body`, `metadata = {}`.

## 5. Error mapping

Non-2xx (or a body with a falsy `success` / a `status >= 400`) raises a typed
error. Base type carries: `message`, `httpStatus`, `apiStatus`, `errors` (array,
may be empty), `raw`, `requestId` (from `x-request-id` if present).

| Condition | Error type |
|---|---|
| HTTP 401 | `AuthenticationError` |
| HTTP 403, body `success:false` and message mentions plan / "tidak menyertakan" | `PlanError` (subtype of `PermissionError`) |
| HTTP 403, otherwise | `PermissionError` |
| HTTP 404 | `NotFoundError` |
| HTTP 408 | `SignatureExpiredError` |
| HTTP 409 | `ConflictError` |
| HTTP 422, or 400 with an `errors` array | `ValidationError` (`.errors` populated) |
| HTTP 429 | `RateLimitError` |
| HTTP 400 (no `errors`) | `BadRequestError` |
| HTTP >= 500 | `ServerError` |
| network / timeout / DNS | `TransportError` |
| anything else non-2xx | `PlasgosApiError` (base) |

> Server quirk: an un-approved key can produce **HTTP 500** from the permission
> middleware instead of 403. `ServerError.message` will contain
> `"You don't have permission"` in that case — surface it verbatim.

## 6. Retry policy

- Retry only `GET` (and only when `maxRetries > 0`).
- Retry on: `TransportError`, `ServerError` (>=500), `RateLimitError` (429).
- Exponential backoff: `base * 2^attempt` with `base = 500ms`, full jitter, capped 8s.
- Never retry `POST` automatically (broadcasts use `request_id`, messages use
  `message_id` — callers can build their own idempotent retry).

## 7. Webhook verification

```
verifyWebhook(headers, rawBody, expectedToken) -> WebhookEvent
```

- Compare `headers['x-hub-token']` to `expectedToken` with a constant-time
  compare. Mismatch / missing ⇒ `WebhookVerificationError`.
- Parse `rawBody` as JSON; invalid ⇒ `WebhookVerificationError`.
- Return the parsed event. Expose an `event` discriminator with constants
  `MESSAGE_RECEIVED = "message.received"`, `MESSAGE_STATUSES = "message.statuses"`.
- There is **no** payload HMAC — do not advertise one.

## 8. Golden signature vectors

Used by every SDK's test-suite (`test/vectors.json` equivalent):

| api_key | secret_key | timestamp | expected signature |
|---|---|---|---|
| `d4c997dd136d31fb9b433b269764a2774d35809e438684c3ad372d27305ac026` | `plg_0512d9ed263f7f22130e04a64f2da5bd0d461f8d398e55d796e6f1a463704266` | `1700000000` | `4b0f9e...` (compute once, then pin) |
| `test_key` | `test_secret` | `1700000000` | pin on first run |

Generate the reference values with the bundled script
`openapi/../scripts` equivalent, or Node:
`node -e "console.log(require('crypto').createHmac('sha256','plg_0512d9ed263f7f22130e04a64f2da5bd0d461f8d398e55d796e6f1a463704266').update('d4c997dd136d31fb9b433b269764a2774d35809e438684c3ad372d27305ac026:1700000000').digest('hex'))"`
