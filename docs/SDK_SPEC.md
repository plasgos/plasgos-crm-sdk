# SDK spec: shared contract (API v3, SDK 2.x)

This document is the single source of truth for the **method surface**, **signing**, **response
model**, **error mapping**, **retry policy** and **webhook verification**. The Node, PHP and Python
SDKs must implement it identically.

The API contract is [`../openapi/openapi-v3.json`](../openapi/openapi-v3.json). It is a copy; the
source of truth lives in the `api-crm` service at
`server/modules/api_integration/docs/openapi/integration-v3.openapi.json`.

When the API changes, update `openapi-v3.json` first, then this file, then the implementations.

The legacy `/api/v1` contract (`LegacyClient`) is in [SDK_SPEC_LEGACY_V1.md](SDK_SPEC_LEGACY_V1.md).

| Package | 2.x status |
|---|---|
| PHP `plasgos/crm-sdk` | **implemented** |
| Node `plasgos-crm-sdk` | **implemented** |
| Python `plasgos-crm-sdk` | **implemented** |

## 1. Client construction

```
Client(apiKey, secretKey, options)        # API v3, /api/v3/*
LegacyClient(apiKey, secretKey, options)  # deprecated, /api/v1/* (see legacy spec)
CredentialsClient(accessToken, fingerprint, options)  # unchanged, /v2/integration/*
AsyncClient(apiKey, secretKey, options)   # Python only: asyncio twin of Client
```

Python's `AsyncClient` has the same resources, method names and parameters as `Client`; every method
is a coroutine, and list methods return an `AsyncPage` (`await page.next_page()`,
`async for item in page.auto_paging()`). `tests/test_v3_async.py` checks the parity.

| Option | Default | Meaning |
|---|---|---|
| `baseUrl` | `production` | `production`, `sandbox`, or an absolute URL (host only; the SDK appends `/api/v3`). |
| `timeout` | 15 s | Timeout for each attempt; a call can override it (§1.1). |
| `maxRetries` | `2` | Automatic retries (§6). |
| `maxRetryWait` | 30 s | Longest `Retry-After` honoured automatically; longer ones are raised to the caller. |
| `userAgent` | `plasgos-crm-sdk-<lang>/<version> <runtime>/<version>` | Sent as `User-Agent`. Integrations append themselves, e.g. `plasgos-wordpress/1.0.0 (+https://shop.example)`. |
| transport | platform default | Injectable HTTP client (PSR-18, `fetch`, `httpx`). |
| `logger` | none | Per-attempt log hook (§1.2). Python uses the `plasgos_crm` stdlib logger instead. |
| `sleeper`, `clock` | real | Test hooks for retry waits and the signing timestamp. |

### 1.1 Per-call options

Every resource method and `request()` takes per-call options as its last argument. They apply to that
call only, including its retries and, for lists, every page `autoPaging()` fetches.

| Option | Node (`RequestOptions`) | PHP (`RequestOptions`) | Python (`options=`, `RequestOptions`) |
|---|---|---|---|
| Idempotency key | `idempotencyKey` (a bare string still works) | own argument `$idempotencyKey` | own argument `idempotency_key` |
| Timeout per attempt | `timeout` (ms) | `timeout` (s; needs the default Guzzle transport, else `LogicException`) | `timeout` (s) |
| Automatic retries | `maxRetries` | `maxRetries` | `max_retries` |
| Extra headers | `headers` | `headers` | `headers` |
| Cancellation | `signal` (`AbortSignal`; also stops pending retries, the abort reason is thrown as is) | — | — |

Headers owned by the SDK are rejected (Node `TypeError`, PHP `InvalidArgumentException`, Python
`ValueError`): `Accept`, `User-Agent`, `Plasgos-Api-Key`, `Plasgos-Timestamp`, `Plasgos-Signature`,
`Idempotency-Key`, `Content-Type`, `Content-Length`, `Host`.

### 1.2 Logging

The SDK records **one event per attempt** with these fields:

| Field | Meaning |
|---|---|
| `method` | HTTP method |
| `path` | Path **without the query**, e.g. `/api/v3/contacts/c_1` |
| `attempt` | 1 for the first try |
| `status` | HTTP status, `null` when no response arrived |
| `duration_ms` | Time of this attempt |
| `request_id` | Server request id, when known |
| `error_code` | `errorCode` of the error, `null` on success |
| `retry_in_ms` | Wait before the next retry, `null` when this attempt is final |

Headers, the query and the body are **never** logged, so the API key, the signature, phone numbers,
e-mail addresses and message text cannot leak into logs.

| | Hook | Level |
|---|---|---|
| Node | `logger: (event) => void` option (camelCase fields) | — |
| PHP | `ClientConfig(logger: LoggerInterface)` (PSR-3), fields in the context | `debug` |
| Python | stdlib `logging.getLogger("plasgos_crm")`, fields in `record.plasgos_crm` | `DEBUG` |

An exception thrown by the logger is swallowed; it never breaks the call.

The base URLs are:

- `production`: `https://wa-client.plasgos.co.id`
- `sandbox`: `https://api-crm.sandbox.plasgos.co.id`

## 2. Signing

Every `/api/v3/*` request except `GET /openapi.json` is signed:

```
timestamp      = floor(now / 1s)
string_to_sign = timestamp + "\n" + UPPER(method) + "\n" + request_target + "\n" + lowercase_hex(sha256(raw_body))
signature      = "v1=" + lowercase_hex(hmac_sha256(secretKey, string_to_sign))

headers:
  Plasgos-Api-Key   = apiKey
  Plasgos-Timestamp = str(timestamp)
  Plasgos-Signature = signature
```

Rules:

- **`request_target`** is the path and query exactly as sent, e.g.
  `/api/v3/contacts?search=budi%20santoso&page=2`. Build the query **once** using RFC 3986 encoding
  (spaces become `%20`), dropping `null` and `""` values and turning booleans into
  `"true"`/`"false"`. Sign that exact string.
- **`raw_body`** is the exact bytes sent. Serialise the body **once** as JSON with unescaped slashes
  and unicode, and send those same bytes. When there is no body, use `""`. An action POST with no
  input sends `{}`.
- **Recompute the timestamp and signature on every attempt.** The server rejects a skew of more
  than 300 s with `401 signature_expired`, and `error.details.server_time` carries the server clock.
- **Golden vectors** are in [`vectors-v3.json`](vectors-v3.json) (`request[]`). They were
  cross-checked against the server implementation.

## 3. Resources

Method names are `camelCase` in Node and PHP, and `snake_case` in Python. Inputs and outputs use the
**wire format** (`snake_case` keys) without renaming, so the OpenAPI schemas apply directly.

`Page` = list result (§4.2). `Response` = single result (§4.1).

| Resource | Method | HTTP | Scope |
|---|---|---|---|
| `me` | `get()` | `GET /me` | `account:read` |
| `channels` | `list()` | `GET /channels` | `channels:read` |
| `endpoints` | `list(params)` → Page | `GET /endpoints` | `channels:read` |
| | `get(endpointId)` | `GET /endpoints/{endpoint_id}` | |
| | `templates(endpointId, params)` → Page | `GET /endpoints/{endpoint_id}/templates` | |
| `templateVariables` | `list()` | `GET /template-variables` | `channels:read` |
| `messages` | `sendOptions(contact)` | `GET /messages/send-options` | `messages:read` |
| | `send(input, idempotencyKey?)` → 202 | `POST /messages` | `messages:write` |
| | `get(messageId)` | `GET /messages/{message_id}` | `messages:read` |
| `contacts` | `list(params)` → Page | `GET /contacts` | `contacts:read` |
| | `lookup(by)` | `GET /contacts/lookup` | |
| | `create(contact, idempotencyKey?)` | `POST /contacts` | `contacts:write` |
| | `upsert(contact, idempotencyKey?)` → 201/200 | `POST /contacts/upsert` | |
| | `get(contactId)` | `GET /contacts/{contact_id}` | `contacts:read` |
| | `update(contactId, changes)` | `PATCH /contacts/{contact_id}` | `contacts:write` |
| `contactGroups` | `list(params)` → Page | `GET /contact-groups` | `contacts:read` |
| `pipelines` | `list()` | `GET /pipelines` | `leads:read` |
| `leads` | `create(lead, idempotencyKey?)` → 201/200 | `POST /leads` | `leads:write` |
| | `get(leadId)` | `GET /leads/{lead_id}` | `leads:read` |
| `broadcasts` | `list(params)` → Page | `GET /broadcasts` | `broadcasts:read` |
| | `create(broadcast, idempotencyKey?)` → 202 | `POST /broadcasts` | `broadcasts:write` |
| | `get(id)` | `GET /broadcasts/{broadcast_id}` | `broadcasts:read` |
| | `recipients(id, params)` → Page | `GET /broadcasts/{broadcast_id}/recipients` | |
| | `cancel(id)` | `POST /broadcasts/{broadcast_id}/cancel` | `broadcasts:write` |
| `followups` | `list(params)` → Page | `GET /followups` | `followups:read` |
| | `get(id)` | `GET /followups/{followup_id}` | |
| | `enrollments(id, params)` → Page | `GET /followups/{followup_id}/enrollments` | |
| | `enroll(id, enrollment, idempotencyKey?)` → 201/200 | `POST /followups/{followup_id}/enrollments` | `followups:write` |
| | `unenroll(id, contactId)` | `DELETE /followups/{followup_id}/enrollments/{contact_id}` | |
| `webhooks` | `list()` | `GET /webhooks` | `webhooks:manage` |
| | `create(subscription)` | `POST /webhooks` (returns `signing_secret` once) | |
| | `update(id, changes)` | `PATCH /webhooks/{webhook_id}` | |
| | `delete(id)` | `DELETE /webhooks/{webhook_id}` | |
| | `test(id)` | `POST /webhooks/{webhook_id}/test` | |
| | `rotateSecret(id)` | `POST /webhooks/{webhook_id}/rotate-secret` | |
| client | `request(method, path, query?, body?, idempotencyKey?)` | escape hatch | |

- **Path ids are URL-encoded per segment.** An id can never alter the path.
- **Server availability** is phased: V1 (`/me`, `/openapi.json`) is live; V2 adds messages and
  endpoints; V3 adds contacts and leads; V4 adds broadcasts and followups; V5 adds webhooks. Calling
  a method whose endpoint is not deployed yet raises `NotFound` with `error_code = route_not_found`.

### 3.4 Content builders

`Content.text(body, previewUrl=false)`,
`Content.image|video|document|media(type, url, caption?, fileName?, mimeType?)`, and
`Content.template(templateId).dynamic(param, placeholder).custom(param, value).location(lat, lng, name?, address?)`
produce these shapes:

```json
{ "type": "text", "text": { "body": "Halo", "preview_url": false } }
{ "type": "image", "media": { "url": "https://…", "caption": null, "file_name": null, "mime_type": null } }
{ "type": "template", "template": { "template_id": "…", "variables": [ { "param": "name", "type": "dynamic", "value": "{{full_name}}" } ] } }
```

`location` is only for templates with a LOCATION header; media headers always use the approved template's media.
The cross-conversation message list (`GET /messages`) is not part of v3.0, so SDKs do not expose `messages.list` yet.

Setting the same `param` twice replaces the earlier value.

## 4. Response model

### 4.1 `Response`

The server returns `{ success: true, message, data, meta }`. The SDK exposes these fields:

| Field | Source |
|---|---|
| `httpStatus` | real HTTP status |
| `message` | `body.message` (Indonesian, user-facing) |
| `data` | `body.data` |
| `meta` | `body.meta` (object) |
| `requestId` | `meta.request_id`, else the `X-Request-Id` header |
| `rateLimit` | `{ limit, remaining, reset }` from the `RateLimit-*` headers (null when absent) |
| `replayed` | `Idempotent-Replayed: true` |
| `headers` | lower-cased response headers |

A 2xx response whose body is not this envelope raises the base error with
`errorCode = unexpected_response`. An empty 2xx body yields `data = null`.

### 4.2 `Page`

A `Page` wraps a list `Response`:

- `items` is always a list.
- `pagination` is `{ page, per_page, total, total_pages, has_next_page, has_prev_page }`.
- `hasNextPage()` and `nextPage()` are available.
- `autoPaging()` lazily yields every item of every page. Each next page re-sends the same query with
  `page` changed.
- The page is iterable and countable over the current `items`.

### 4.3 Types

Request and response types are **generated** from `openapi/openapi-v3.json` by
`scripts/generate-types.mjs` and never edited by hand; CI runs it with `--check`, so the types cannot
drift from the API.

| | Node | Python | PHP |
|---|---|---|---|
| Schemas | `Types.Contact`, `Types.Message`, … (`export type * as Types`) | `plasgos_crm.v3.types` (`TypedDict`) | `@phpstan-type` aliases on `Plasgos\CrmSdk\Types\ApiTypes` (array shapes) |
| Per operation | `Types.Operations["sendMessage"]` → `query`, `body`, `data`, `meta` (+ `item` for lists) | `SendMessageBody`, `ListContactsQuery`, `GetContactData`, `ListContactsItem`, `SendMessageMeta` | same names as Python, as aliases |
| Resource methods | params, body, `ApiResponse<data, meta>` and `Page<item>` typed | params, body, `ApiResponse[Data]` and `Page[Item]` typed | params, body, `Response<Data, Meta>` and `Page<Item>` typed (generic `@template`) |
| Checked in CI | `tsc` + `expectTypeOf` | `mypy --strict` (`tests/test_v3_types.py`, `tests/test_v3_async.py`) | PHPStan level max (`src`, `tests/Types`) |

Fields that take a content builder (`content` of `messages.send` and `broadcasts.create`) also accept
`TemplateContent`. Python `Content.text()` / `media()` return the matching `TypedDict`.

## 5. Error mapping

Errors use the envelope `{ success:false, message, error:{ code, type, details, fields, doc_url }, meta:{ request_id } }`.

Every error carries:

- `message`
- `httpStatus`
- `errorCode` (= `error.code`; branch on this)
- `errorType`
- `details`
- `fields` (`[{field, code, message}]`)
- `docUrl`
- `requestId`
- `retryAfter` (from the `Retry-After` header)
- `raw`

PHP and Python also keep the legacy `errors` view (`[{message, path[]}]`).

| Condition | Error type (PHP name) |
|---|---|
| 401 `signature_expired` | `SignatureExpiredException` (also an `AuthenticationException`) |
| 401 | `AuthenticationException` |
| 403 `not_entitled` | `PlanException` (also a `PermissionException`) |
| 403 | `PermissionException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 400 without `fields` | `BadRequestException` |
| 400 with `fields`, 413, 422 | `ValidationException` |
| 429 | `RateLimitException` (`errorCode` `rate_limited` or `daily_quota_exceeded`) |
| 502 | `ChannelException` (also a `ServerException`) |
| 503 | `ServiceUnavailableException` (also a `ServerException`) |
| other ≥ 500 | `ServerException` |
| no response (DNS, timeout, TLS) | `TransportException` (`errorCode = transport_error`) |
| other non-2xx | base `PlasgosApiException` |

If the error body is not JSON, `errorCode` is `unexpected_response`.

## 6. Idempotency & retry policy

**Idempotency key.** `POST` without an explicit key gets a UUIDv4 `Idempotency-Key`, generated
**once per call** and reused on every retry. Integrations should pass meaningful keys per intent,
e.g. `wc-1234-processing`.

The server applies these rules to the key:

| Case | Server behaviour |
|---|---|
| Same key and same body | Replays the first response (`replayed = true`) |
| Same key and a different body | `422 idempotency_key_reused` |
| Same key while the first request is still in flight | `409 idempotency_in_progress` |
| First response was 5xx | Not stored, so retrying with the key is safe |

**Retries.** `maxRetries` applies to automatic retries.

- **Retried for every method:**
  - `TransportException`
  - `ChannelException` (502)
  - `ServiceUnavailableException` (503)
  - `RateLimitException` with `errorCode = rate_limited`, waiting `Retry-After` when it is at most
    `maxRetryWait`; a longer wait is raised instead.
  - `ConflictException` with `errorCode = idempotency_in_progress` (409): the first request with the
    same key is still running — typically after a client-side timeout — and the retry returns its
    stored answer once it finishes.
- **Retried only for `GET`:** a plain `ServerException` (500), because a 500 on a write may have
  partially run.
- **Never retried:**
  - `daily_quota_exceeded`
  - any other 4xx (including other 409s such as `identity_taken`)

When there is no `Retry-After`, the wait is `random(0, min(8s, 0.5s × 2^attempt))` (full jitter).

## 7. Webhook verification (v3)

```
verify(rawBody, headers, secrets, tolerance = 300, now?) -> event
```

Headers (any casing, including `HTTP_*` server keys):

- `Plasgos-Webhook-Id`
- `Plasgos-Webhook-Timestamp`
- `Plasgos-Webhook-Signature = "v1=" + hex(hmac_sha256(secret, id + "." + timestamp + "." + raw_body))`

`WebhookVerificationException` is raised when:

- a header is missing;
- the timestamp is not an integer or is outside `tolerance`;
- there is no `v1=` part;
- no secret matches (comparison is constant-time; `secrets` may hold the current and previous secret
  during rotation);
- the body is not JSON, has no `id`/`type`, or `id` differs from the header.

Also expose:

- `signatureHeaders(id, timestamp, body, secret)` for tests;
- event type constants: `message.received`, `message.status_updated`, `contact.created`,
  `contact.updated`, `lead.created`, `lead.stage_changed`, `deal.won`, `deal.lost`,
  `broadcast.completed`, `followup.enrollment.completed`, `followup.enrollment.stopped`,
  `webchat.visitor.identified`, `webhook.test`.

Receivers must drop event ids they have already processed, because deliveries can repeat.
The golden vector is in [`vectors-v3.json`](vectors-v3.json) under `webhook[]`.

The legacy `x-hub-token` verifier stays available under `Legacy\` for `/api/v1` webhooks.

## 8. Webchat identity token

```
signIdentity(secret, identity, widgetId?, ttl = 3600, now?, jti?) -> string     (Python: sign_identity)
```

Tells the website chat widget who the signed-in customer is (trust level `verified`). Sign on the
website's **server** with the widget's identity secret (`wcs_…`, shown once when the widget is created or
rotated); the secret never reaches the browser. The page passes the token to
`Plasgos("boot", { widgetId, identityToken })` or `Plasgos("identify", { token })`.

Token = JWT HS256:

```
header_json  = {"alg":"HS256","typ":"JWT"}
payload_json = {"sub", "email"?, "email_verified"?, "name"?, "phone"?, "wid"?, "iat", "exp", "jti"}   (keys in this order)
token        = base64url(header_json) + "." + base64url(payload_json) + "." + base64url(hmac_sha256(secret, first two parts))
```

Rules:

- **JSON** is compact with unescaped slashes, unicode and line terminators (U+2028/U+2029), so tokens
  are byte-identical in the three SDKs. base64url without padding.
- **`identity.sub`** — string (an integer is turned into a string), not blank, at most 255 UTF-16 code
  units. `email`, `name`, `phone` are optional strings; `null` and `""` are left out; any other type is
  an argument error.
- **`identity.email_verified`** — optional boolean. Claim `email_verified: true` is written (right after
  `email`) ONLY when it is `true` AND `email` is present; `false`/`null`/absent leave it out, so tokens without it
  are unchanged. Any other type is an argument error. The server links the visitor to an EXISTING CRM contact by
  email only when this claim is `true`: set it only when the website itself verified the customer owns the
  address (confirmation link, OAuth). Otherwise the email just fills empty contact fields — anyone could sign up
  with someone else's address.
- **`widgetId`** → claim `wid`: the server then accepts the token only for that widget.
- **`ttl`** — integer 1–86400 seconds (the server rejects lifetimes over 24 hours). `exp = iat + ttl`,
  `iat = now` (Unix seconds).
- **`jti`** — random UUID v4 by default; at most 128 characters. The server accepts a `jti` for one
  visitor only, so sign a fresh token per page render / sign-in.
- Invalid input raises the language's argument error (`TypeError`/`RangeError`,
  `\InvalidArgumentException`, `ValueError`) whose message starts with `Webchat.signIdentity` /
  `Webchat::signIdentity` / `Webchat.sign_identity`.

Golden vectors: [`vectors-v3.json`](vectors-v3.json) `webchat_identity[]` (expected header/payload JSON
and token) and `webchat_identity_invalid[]`. They were cross-checked against the server verifier
(api-crm `tests/webchat_identity.integration.test.js`).
