# plasgos-crm-sdk

Official Node.js / TypeScript SDK for the **Plasgos CRM — API Integration** API
(the `api-crm` service).

- **Zero runtime dependencies** — uses Node 18+ `fetch` and `node:crypto`.
- **Dual module format** — works unchanged with `import` (ESM) and `require` (CommonJS).
- **Automatic HMAC request signing** for every `/api/v1/*` call.
- **Typed** models, typed error hierarchy, one normalised response envelope.
- **Webhook verification** helper (+ Express middleware).
- **Separate client** for the credential-management endpoints (`/v2/integration/*`).

> This README is the complete usage reference. A longer, task-oriented guide
> (Bahasa Indonesia, with PHP/Python side by side) lives in
> [`docs/sdk-guide/`](https://github.com/plasgos/plasgos-crm-sdk/tree/main/docs/sdk-guide).

---

## Table of contents

- [Install](#install)
- [Module formats — ESM & CommonJS](#module-formats--esm--commonjs)
- [Authentication](#authentication)
- [Creating a client](#creating-a-client)
- [Configuration options](#configuration-options)
- [Response envelope](#response-envelope)
- [Error handling](#error-handling)
- [Messages (unofficial WhatsApp)](#messages-unofficial-whatsapp)
- [Accounts](#accounts)
- [References (template variables, contacts)](#references)
- [Official WhatsApp / WABA](#official-whatsapp--waba)
  - [Accounts & phone numbers](#official-accounts--phone-numbers)
  - [Templates](#official-templates)
  - [Send a template message](#send-a-template-message)
  - [Broadcasts](#broadcasts)
  - [Shared-WABA OTP](#shared-waba-otp)
- [Credential management](#credential-management)
- [Webhooks](#webhooks)
- [Utilities](#utilities)
- [Method reference](#method-reference)
- [Permissions](#permissions)
- [TypeScript](#typescript)
- [License](#license)

---

## Install

```bash
npm install plasgos-crm-sdk
# pnpm add plasgos-crm-sdk   •   yarn add plasgos-crm-sdk
```

Requires **Node.js 18 or newer**.

---

## Module formats — ESM & CommonJS

The package ships both builds. The only thing that changes between module
systems is the **import line** and whether you can use **top-level `await`**.
Every method call shown later is identical in both.

### ESM (`"type": "module"`, `.mjs`, TypeScript, bundlers)

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";
import { verifyWebhook, WebhookEventName } from "plasgos-crm-sdk/webhooks";

const client = new PlasgosCrmClient(
  process.env.PLASGOS_API_KEY,
  process.env.PLASGOS_SECRET_KEY,
  { baseUrl: "production" },
);

// top-level await is available in ESM
const res = await client.accounts.list();
console.log(res.data);
```

### CommonJS (default `.js`, `.cjs`)

```js
const { PlasgosCrmClient } = require("plasgos-crm-sdk");
const { verifyWebhook, WebhookEventName } = require("plasgos-crm-sdk/webhooks");

const client = new PlasgosCrmClient(
  process.env.PLASGOS_API_KEY,
  process.env.PLASGOS_SECRET_KEY,
  { baseUrl: "production" },
);

// wrap awaits in an async function in CommonJS
(async () => {
  const res = await client.accounts.list();
  console.log(res.data);
})();
```

Notes:

- `verifyWebhook`, `plasgosWebhook`, `WebhookEventName` and the webhook types are
  **also re-exported from the package root**, so `plasgos-crm-sdk/webhooks` is
  optional — `require("plasgos-crm-sdk")` / `import … from "plasgos-crm-sdk"`
  expose them too.
- Type declarations are provided for both resolutions (`.d.ts` / `.d.cts`), so
  `moduleResolution` `node16` / `nodenext` / `bundler` all resolve correctly.

From here on, examples use ESM `import`. For CommonJS, swap the import line for
`const { … } = require("plasgos-crm-sdk")` and wrap `await` in an `async`
function.

---

## Authentication

There are **two** auth mechanisms — do not mix them.

| | Signed API | Credential management |
|-|------------|-----------------------|
| Endpoints | `/api/v1/*` | `/v2/integration/*` |
| Headers | `x-api-key`, `x-timestamp`, `x-signature` | `Authorization: Bearer <token>`, `fingerprint` |
| Client | `PlasgosCrmClient` | `PlasgosCrmCredentialsClient` |
| Credentials | `api_key` + `secret_key` (approved) | CRM user session token + fingerprint |

### How the signature works

For every `/api/v1/*` request the SDK sends:

| Header | Value |
|--------|-------|
| `x-api-key` | your `api_key` (64-char hex) |
| `x-timestamp` | current Unix time **in seconds** |
| `x-signature` | `lowercase_hex( HMAC_SHA256( secret_key, "{api_key}:{timestamp}" ) )` |

- Only `"{api_key}:{timestamp}"` is signed — **not** the method, path, query or body.
- `secret_key` is the plaintext value (`plg_` + hex) returned when the key is
  approved / regenerated.
- The timestamp must be within **300 seconds** of server time, otherwise the
  request fails with **HTTP 408** (`SignatureExpiredError`). Keep your server
  clock synced (NTP).
- The SDK recomputes the signature on **every** request; nothing is cached.

You never set these headers yourself. To inspect what would be sent:

```js
client.signatureHeaders();            // { "x-api-key", "x-timestamp", "x-signature" }
client.signatureHeaders(1700000000);  // with a fixed timestamp
```

### Base URLs

| `baseUrl` value | Resolves to |
|-----------------|-------------|
| `"production"` (default) | `https://wa-client.plasgos.co.id` |
| `"sandbox"` | `https://api-crm.sandbox.plasgos.co.id` |
| any absolute URL | used as-is |

```js
import { BASE_URLS, resolveBaseUrl } from "plasgos-crm-sdk";
BASE_URLS.production;          // "https://wa-client.plasgos.co.id"
resolveBaseUrl("sandbox");     // "https://api-crm.sandbox.plasgos.co.id"
```

---

## Creating a client

### ESM

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const client = new PlasgosCrmClient(apiKey, secretKey, {
  baseUrl: "sandbox",
  timeout: 15_000,
  maxRetries: 2,
  userAgent: "my-app/1.0.0",
});
```

### CommonJS

```js
const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const client = new PlasgosCrmClient(apiKey, secretKey, {
  baseUrl: "sandbox",
  timeout: 15_000,
  maxRetries: 2,
  userAgent: "my-app/1.0.0",
});
```

`apiKey` and `secretKey` are required; passing empty values throws `TypeError`.

---

## Configuration options

`new PlasgosCrmClient(apiKey, secretKey, options)` — every option is optional:

| Option | Type | Default | Meaning |
|--------|------|---------|---------|
| `baseUrl` | `"production" \| "sandbox" \| string` | `"production"` | Target environment or absolute URL |
| `timeout` | `number` (ms) | `15000` | Per-request timeout → `TransportError` on expiry |
| `maxRetries` | `number` | `2` | Retries for **idempotent GET only** |
| `signatureTtl` | `number` (s) | `300` | Informational; the server enforces the real TTL |
| `userAgent` | `string` | `plasgos-crm-sdk-node/<version>` | `User-Agent` header |
| `fetch` | `typeof fetch` | `globalThis.fetch` | Inject a custom fetch (tests, proxies, custom runtimes) |

### Retry behaviour

- Only `GET` requests are retried, and only when `maxRetries > 0`.
- Retried on: `TransportError` (network/timeout), `ServerError` (≥ 500),
  `RateLimitError` (429).
- Exponential backoff `500ms · 2^attempt` with full jitter, capped at 8 s.
- `POST` requests (send message, broadcast) are **never** auto-retried — build
  your own idempotent retry using `message_id` / `request_id`.

---

## Response envelope

Every successful call resolves to a normalised `Envelope`:

```ts
interface Envelope<T = unknown> {
  status: number | boolean;          // status the body reported, else the HTTP status
  message: string | null;
  data: T;                            // the payload
  metadata: Record<string, unknown>; // `metadata` OR `meta` from the server, or {}
  raw: unknown;                       // the untouched parsed body
  httpStatus: number;                 // the real HTTP status code
}
```

The server is inconsistent (`{status,message,data,metadata}`,
`{success,data}`, `{status,message,data,meta}`, …); the SDK collapses all of
them into the shape above. Use `raw` if you need a field the SDK does not map.

```js
const res = await client.accounts.list();
res.data;         // Account[]
res.httpStatus;   // 200
res.metadata;     // { count, timestamp, ... }
```

---

## Error handling

A non-2xx response (or a body with `success: false`, or a body `status >= 400`)
throws a typed error.

```
PlasgosApiError                 base — every SDK error
├── AuthenticationError         401
├── PermissionError             403 (missing scope / context)
│   └── PlanError               403 (plan has no API access)
├── NotFoundError               404
├── SignatureExpiredError       408 (timestamp outside the window)
├── ConflictError               409 (duplicate message_id)
├── ValidationError             400/422 — see .errors
├── BadRequestError             400 (no structured errors)
├── RateLimitError              429 (daily API hit quota)
├── ServerError                 >= 500
└── TransportError              network / timeout / DNS (no response)

WebhookVerificationError        (not a PlasgosApiError — thrown by verifyWebhook)
```

Every error carries: `.message`, `.httpStatus`, `.apiStatus`, `.errors`
(`{ message, path }[]`), `.raw`, `.requestId`.

### ESM

```js
import {
  PlasgosCrmClient,
  ValidationError,
  RateLimitError,
  SignatureExpiredError,
  PlanError,
  PlasgosApiError,
} from "plasgos-crm-sdk";

try {
  await client.messages.send(/* … */);
} catch (err) {
  if (err instanceof ValidationError) {
    for (const issue of err.errors) console.error(issue.path, issue.message);
  } else if (err instanceof RateLimitError) {
    // back off / re-queue
  } else if (err instanceof SignatureExpiredError) {
    // check the server clock (NTP)
  } else if (err instanceof PlanError) {
    // subscription plan has no API access
  } else if (err instanceof PlasgosApiError) {
    console.error(err.httpStatus, err.message, err.raw);
  } else {
    throw err; // not an SDK error
  }
}
```

### CommonJS

```js
const {
  ValidationError, RateLimitError, SignatureExpiredError, PlasgosApiError,
} = require("plasgos-crm-sdk");

async function run() {
  try {
    await client.messages.send(/* … */);
  } catch (err) {
    if (err instanceof ValidationError) console.error(err.errors);
    else if (err instanceof RateLimitError) console.error("slow down");
    else if (err instanceof SignatureExpiredError) console.error("clock skew");
    else if (err instanceof PlasgosApiError) console.error(err.httpStatus, err.message);
    else throw err;
  }
}
```

> **Known server quirk:** an API key that has not been approved yet can return
> **HTTP 500** (`ServerError`) with a message containing
> `"You don't have permission"` instead of 403.

---

## Messages (unofficial WhatsApp)

Send through WhatsApp accounts connected in the CRM by QR/pairing. For the
official WhatsApp Business API see [Official WhatsApp / WABA](#official-whatsapp--waba).

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `client.messages.send(input)` | `POST /api/v1/messages` | `message:send` |
| `client.messages.list()` | `GET /api/v1/messages` | `message:list` |
| `client.messages.get(messageId)` | `GET /api/v1/messages/{id}` | `message:detail` |

### `send(input)`

| Field | Required | Rule |
|-------|----------|------|
| `messageId` | no | Globally unique. Omit → SDK generates a UUIDv4 and returns it on `result.messageId`. Duplicate → `ConflictError` (409). |
| `channel` | yes | `"whatsapp"` \| `"telegram"` \| `"messanger"` \| `"instagram"` (`messanger` spelling is intentional — it matches the server) |
| `account.accountId` | yes | Mongo ObjectId of a **connected** account (see [Accounts](#accounts)) |
| `receiver.phoneNumber` | yes | matches `^(?:\+62\|62\|08)[0-9]{7,12}$`, max 15 chars |
| `receiver.name` | yes | non-empty |
| `content.type` | yes | `"text"` \| `"image"` \| `"video"` \| `"document"` |
| `content.data` | yes | depends on `type` (below) |

Content by type:

| `type` | `content.data` fields |
|--------|-----------------------|
| `text` | `text` (required) |
| `image` | `url` (required), `caption` |
| `video` | `url` (required), `caption` |
| `document` | `url` (required), `caption`, `mimeType`, `fileName` |

Returns `Envelope<Message> & { messageId: string }`.

### ESM

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const client = new PlasgosCrmClient(apiKey, secretKey, { baseUrl: "production" });

// text
const r1 = await client.messages.send({
  channel: "whatsapp",
  account: { accountId: "665f1c2e9a1b2c3d4e5f6a7b" },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: { type: "text", data: { text: "Pesanan #INV-001 diproses." } },
});
console.log(r1.messageId, r1.data.sent);

// image with your own message_id (for correlation / safe retry)
await client.messages.send({
  messageId: "order-INV-001-shipped",
  channel: "whatsapp",
  account: { accountId: "665f1c2e9a1b2c3d4e5f6a7b" },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: {
    type: "image",
    data: { url: "https://cdn.example.com/resi.jpg", caption: "Resi pengiriman" },
  },
});

// document
await client.messages.send({
  channel: "whatsapp",
  account: { accountId: "665f1c2e9a1b2c3d4e5f6a7b" },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: {
    type: "document",
    data: {
      url: "https://cdn.example.com/invoice-INV-001.pdf",
      mimeType: "application/pdf",
      fileName: "invoice-INV-001.pdf",
      caption: "Invoice terlampir",
    },
  },
});

// list & detail
const all = await client.messages.list();
const one = await client.messages.get("order-INV-001-shipped");
```

### CommonJS

```js
const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const client = new PlasgosCrmClient(apiKey, secretKey, { baseUrl: "production" });

(async () => {
  const r1 = await client.messages.send({
    channel: "whatsapp",
    account: { accountId: "665f1c2e9a1b2c3d4e5f6a7b" },
    receiver: { phoneNumber: "6281234567890", name: "Budi" },
    content: { type: "text", data: { text: "Pesanan #INV-001 diproses." } },
  });
  console.log(r1.messageId);

  const all = await client.messages.list();
  const one = await client.messages.get(r1.messageId);
})();
```

---

## Accounts

Connected unofficial WhatsApp accounts.

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `client.accounts.list()` | `GET /api/v1/accounts` | `account:list` |
| `client.accounts.get(accountId)` | `GET /api/v1/accounts/{id}` | `account:detail` |

`data` per account: `{ id: string, name: string \| null, phone_number: string \| null }`.
Use `id` as `account.accountId` when sending messages.

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";
// CommonJS: const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const accounts = await client.accounts.list();
const detail = await client.accounts.get(accounts.data[0].id);
```

---

## References

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `client.references.variables()` | `GET /api/v1/references/variables` | — (signature only) |
| `client.references.contacts({ page?, limit?, search? })` | `GET /api/v1/references/contacts` | — (signature only) |

### Template variables

Returns the variables usable in WABA templates:
`{ label: string, value: "{{name}}", example: string }[]`.

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";
// CommonJS: const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const vars = await client.references.variables();
vars.data.forEach((v) => console.log(v.value, "→", v.example));
```

### Contacts (paginated)

```js
const res = await client.references.contacts({ page: 1, limit: 50, search: "budi" });
console.log(res.data);                  // contact rows
console.log(res.metadata.total_pages);  // { page, limit, total, total_pages, timestamp }
```

Iterate all pages:

```js
async function* allContacts(client, pageSize = 100) {
  for (let page = 1; ; page++) {
    const res = await client.references.contacts({ page, limit: pageSize });
    yield* res.data;
    if (page >= Number(res.metadata.total_pages ?? 1)) break;
  }
}

for await (const contact of allContacts(client)) handle(contact);
```

---

## Official WhatsApp / WABA

Namespace `client.official.*`. Typical flow:

1. `official.accounts.list()` → get a WABA account + its phone numbers
2. `official.accounts.phoneNumbers(accountId)` → pick a `phone_number_id`
3. `official.templates.list(accountId, { category })` → pick an `APPROVED` template
4. `official.messages.sendTemplate(…)` or `official.broadcasts.create(…)`

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `official.accounts.list()` | `GET /api/v1/official/accounts` | `official:account:list` |
| `official.accounts.phoneNumbers(accountId)` | `GET …/official/accounts/phone-numbers/{accountId}` | `official:phone-numbers:list` |
| `official.templates.list(accountId, { category? })` | `GET …/official/templates/{accountId}` | `official:templates:list` |
| `official.messages.sendTemplate(input)` | `POST …/official/messages` | `official:message:send` |
| `official.broadcasts.create(input)` | `POST …/official/broadcasts` (→ 201) | `official:broadcast:send` |
| `official.broadcasts.list(params)` | `GET …/official/broadcasts` | `official:broadcast:list` |
| `official.broadcasts.monitoring(broadcastId)` | `GET …/official/broadcasts/{id}/monitoring` | `official:broadcast:monitoring` |
| `official.sharedWaba.sendOtp(serialToken, input)` | `POST …/official/shared-waba/otp/{token}` | `official:message:send` |

### Official accounts & phone numbers

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";
// CommonJS: const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const accounts = await client.official.accounts.list();
const accountId = accounts.data[0].id;

const phones = await client.official.accounts.phoneNumbers(accountId);
const phoneNumberId = phones.data[0].phone_number_id;
```

### Official templates

```js
const tpl = await client.official.templates.list(accountId, { category: "UTILITY" });
// category: "MARKETING" | "UTILITY" | "AUTHENTICATION" | "OTP" (optional)
// only APPROVED templates are returned
```

### Send a template message

`input`:

| Field | Notes |
|-------|-------|
| `phoneNumberId` | from `official.accounts.phoneNumbers` |
| `to` | destination number |
| `template.id` | template id (≥ 10 chars) |
| `template.name` | `^[a-z0-9_]+$`, ≥ 3 chars |
| `template.category` | `"TRANSACTIONAL" \| "MARKETING" \| "OTP" \| "UTILITY" \| "AUTHENTICATION"` |
| `template.parameters.variables[]` | `{ type: "dynamic" \| "custom", name, value }` matching the placeholders in Meta |
| `template.parameters.attachment` | for IMAGE/VIDEO/DOCUMENT headers — `{ type, link, mimeType, fileName, originalName?, fileSize? }` |

For `OTP` / `AUTHENTICATION` templates the server can generate the OTP code if
you don't pass one.

#### ESM

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const client = new PlasgosCrmClient(apiKey, secretKey, { baseUrl: "production" });

// plain variables
await client.official.messages.sendTemplate({
  phoneNumberId: "123456789012345",
  to: "6281234567890",
  template: {
    id: "1234567890123456",
    name: "order_update_v1",
    category: "UTILITY",
    parameters: {
      variables: [
        { type: "custom", name: "1", value: "Budi" },
        { type: "custom", name: "2", value: "INV-20260406-001" },
      ],
    },
  },
});

// with a document header
await client.official.messages.sendTemplate({
  phoneNumberId: "123456789012345",
  to: "6281234567890",
  template: {
    id: "1234567890123456",
    name: "invoice_v2",
    category: "UTILITY",
    parameters: {
      variables: [{ type: "custom", name: "1", value: "Budi" }],
      attachment: {
        type: "document",
        link: "https://cdn.example.com/INV-001.pdf",
        mimeType: "application/pdf",
        fileName: "INV-001.pdf",
        originalName: "INV-001.pdf",
        fileSize: 20480,
      },
    },
  },
});
```

#### CommonJS

```js
const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const client = new PlasgosCrmClient(apiKey, secretKey, { baseUrl: "production" });

(async () => {
  await client.official.messages.sendTemplate({
    phoneNumberId: "123456789012345",
    to: "6281234567890",
    template: {
      id: "1234567890123456",
      name: "order_update_v1",
      category: "UTILITY",
      parameters: { variables: [{ type: "custom", name: "1", value: "Budi" }] },
    },
  });
})();
```

### Broadcasts

`create(input)` returns **HTTP 201**. Max **1000 recipients per day**, grouped by
the `scheduledAt` date. `requestId` is the idempotency key.

| Field | Notes |
|-------|-------|
| `accountId`, `templateId`, `phoneNumberId` | as above |
| `name` | broadcast name; `description` optional |
| `scheduledAt` | `"YYYY-MM-DD HH:mm:ss"` |
| `requestId` | idempotency key |
| `variables[]` | `{ name, value }` global variables |
| `location` | for LOCATION templates |
| `recipients[]` | `{ phoneNumber, attachment?: { type, link, mimeType, fileName } }` — `attachment` = per-recipient media for IMAGE/VIDEO/DOCUMENT headers |

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";
// CommonJS: const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const created = await client.official.broadcasts.create({
  accountId: "665f1c2e9a1b2c3d4e5f6a7b",
  templateId: "1234567890123456",
  phoneNumberId: "123456789012345",
  name: "Broadcast Promo April",
  description: "Per-recipient media",
  scheduledAt: "2026-04-08 10:00:00",
  requestId: "broadcast_req_20260407_001",
  variables: [{ name: "event_name", value: "Seminar April" }],
  recipients: [
    { phoneNumber: "628123456700" },
    {
      phoneNumber: "628123456701",
      attachment: {
        type: "image",
        link: "https://cdn.example.com/u2.png",
        mimeType: "image/png",
        fileName: "u2.png",
      },
    },
  ],
});
console.log(created.httpStatus);      // 201
console.log(created.data);            // { broadcast_id, ... }

// list + monitoring
const list = await client.official.broadcasts.list({ page: 1, limit: 10, status: "completed" });
const mon = await client.official.broadcasts.monitoring(list.data[0].id);
// list params: { page?, limit?, status?, search? }
// mon.data: delivery stats, progress, funnel, failure analysis
```

### Shared-WABA OTP

Send an OTP through a shared WABA serial token.

```js
import { PlasgosCrmClient } from "plasgos-crm-sdk";
// CommonJS: const { PlasgosCrmClient } = require("plasgos-crm-sdk");

const otp = await client.official.sharedWaba.sendOtp("srl_abcdef123456", {
  to: "6281234567890",   // min 8 chars
  otpCode: "123456",      // optional — omit to let the server generate it
  metadata: { source: "checkout" }, // optional free-form object
});
console.log(otp.data);   // { message_id, otp_code, serial_token }
```

---

## Credential management

Endpoints `/v2/integration/*` manage the API credential and register the
webhook. **Different auth**: the CRM user session, not the signature.

```js
// ESM
import { PlasgosCrmCredentialsClient } from "plasgos-crm-sdk";
// CommonJS
const { PlasgosCrmCredentialsClient } = require("plasgos-crm-sdk");

const creds = new PlasgosCrmCredentialsClient(accessToken, fingerprint, {
  baseUrl: "production",
  // timeout?, maxRetries?, userAgent?, fetch? — same as PlasgosCrmClient (no signatureTtl)
});
```

`accessToken` is the exact token string your CRM session issued (the SDK does
not encrypt/refresh it); `fingerprint` is the device fingerprint bound to it.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `creds.credentials.get()` | `GET /v2/integration` | Current credential, or `data: null` if never requested |
| `creds.credentials.request()` | `POST /v2/integration` | Request access — creates a `pending` credential |
| `creds.credentials.regenerate()` | `POST /v2/integration/regenerate` | Rotate `api_key` + `secret_key` (only when `active`) |
| `creds.credentials.messages({ page?, limit? })` | `GET /v2/integration/messages` | Paginated history of messages sent via the API |
| `creds.credentials.subscribeWebhook({ webhookUrl, verifyToken? })` | `POST /v2/integration/webhook` | Register + verify your webhook endpoint |

```js
// request → (admin approves + sets permissions) → get
await creds.credentials.request();
const current = await creds.credentials.get();
if (current.data) {
  console.log(current.data.api_key, current.data.secret_key, current.data.status);
  // ApiCredential: { api_key, secret_key, permissions, status, approved_at, requested_at?, regenerated_at? }
}

// rotate (returns the new secret_key in plaintext once)
const rotated = await creds.credentials.regenerate();

// message history
const hist = await creds.credentials.messages({ page: 1, limit: 20 });

// register the webhook (endpoint must already be live — see below)
await creds.credentials.subscribeWebhook({
  webhookUrl: "https://app.example.com/webhooks/plasgos",
  verifyToken: "a-shared-secret-you-choose",
});
```

`request()` throws `BadRequestError` (400) if a credential already exists
(`active` / `pending` / `revoked`). `regenerate()` throws `NotFoundError` (404)
if none exists and `BadRequestError` (400) if the status isn't `active`.

---

## Webhooks

Plasgos CRM pushes events to the URL you registered with `subscribeWebhook`.

| Fact | Value |
|------|-------|
| Method | `POST` JSON |
| Identity header | `x-hub-token: <verify_token>` (there is **no** payload HMAC) |
| Public events | `message.received`, `message.statuses` |
| Delivery timeout | 10 s — respond `2xx` quickly |
| Retry | 3× with exponential backoff, then dropped |

Your endpoint must handle **two** things:

1. **Verification (GET)** — only during `subscribeWebhook`. The server calls
   `GET <webhookUrl>?verify_token=<token>` with header `x-hub-token: <token>`;
   reply `200` and echo the token in the body
   (`{ "verify_token": "<token>" }`).
2. **Events (POST)** — verify `x-hub-token`, process, reply `200`.

### Payload shape

```json
{
  "event": "message.received",
  "event_name": "message.received",
  "event_version": 1052,
  "event_at": "2026-04-08T10:32:15.000Z",
  "timestamp": 1775644335,
  "source": "crm_chatroom",
  "channel": "whatsapp",
  "provider": "official",
  "user": { "id": 42 },
  "account": { "phone_number_id": "123456789012345" },
  "conversation": { "conversation_id": "conv_…", "conversation_status": "open" },
  "message": { "id": "wamid.…", "type": "text", "direction": "inbound",
               "content": { "type": "text", "text": "Halo admin" } },
  "statuses": null,
  "recipient": null,
  "meta": { "emitted_via": "socket_and_webhook" },
  "data": {}
}
```

- `message.received` — inbound customer message (`message` populated).
- `message.statuses` — outbound status change `sent`/`delivered`/`read`/`failed`
  (`statuses` populated).

### `verifyWebhook(headers, rawBody, { expectedToken })`

- `headers` — Node `IncomingHttpHeaders`, a `Headers` instance, or a `Map` (case-insensitive).
- `rawBody` — the **raw** request body as `string` or `Buffer` (not `JSON.parse`d).
- Returns the parsed event. Throws `WebhookVerificationError` on token mismatch
  or invalid JSON. Omit `expectedToken` to skip the token check (parse only).

#### ESM

```js
import { verifyWebhook, WebhookEventName, WebhookVerificationError } from "plasgos-crm-sdk/webhooks";
// also available from the root: import { verifyWebhook } from "plasgos-crm-sdk";

try {
  const event = verifyWebhook(req.headers, rawBody, {
    expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN,
  });

  if (event.event === WebhookEventName.MESSAGE_RECEIVED) {
    // event.message, event.conversation, ...
  } else if (event.event === WebhookEventName.MESSAGE_STATUSES) {
    // event.statuses
  }
} catch (err) {
  if (err instanceof WebhookVerificationError) {
    // reject with 401
  }
}
```

#### CommonJS

```js
const { verifyWebhook, WebhookEventName, WebhookVerificationError } = require("plasgos-crm-sdk/webhooks");

function handle(headers, rawBody) {
  const event = verifyWebhook(headers, rawBody, {
    expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN,
  });
  if (event.event === WebhookEventName.MESSAGE_STATUSES) {
    // ...
  }
  return event;
}
```

### Express — bundled middleware

`plasgosWebhook({ expectedToken })` verifies the request, puts the parsed event
on `req.plasgosEvent`, and responds `401` automatically on failure. It needs the
**raw body**, so mount a text/raw body parser before it.

#### ESM

```js
import express from "express";
import { plasgosWebhook, WebhookEventName } from "plasgos-crm-sdk/webhooks";

const app = express();

// verification (GET) — used once by subscribeWebhook
app.get("/webhooks/plasgos", (req, res) => {
  res.status(200).json({ verify_token: req.query.verify_token });
});

// events (POST)
app.post(
  "/webhooks/plasgos",
  express.text({ type: () => true }),
  plasgosWebhook({ expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN }),
  (req, res) => {
    const event = req.plasgosEvent;
    if (event.event === WebhookEventName.MESSAGE_RECEIVED) {
      // enqueue for async processing — don't block the response
    }
    res.sendStatus(200);
  },
);
```

#### CommonJS

```js
const express = require("express");
const { plasgosWebhook, WebhookEventName } = require("plasgos-crm-sdk/webhooks");

const app = express();

app.get("/webhooks/plasgos", (req, res) =>
  res.status(200).json({ verify_token: req.query.verify_token }),
);

app.post(
  "/webhooks/plasgos",
  express.text({ type: () => true }),
  plasgosWebhook({ expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN }),
  (req, res) => {
    console.log(req.plasgosEvent.event);
    res.sendStatus(200);
  },
);
```

### Fastify / generic

```js
import { verifyWebhook } from "plasgos-crm-sdk/webhooks";
// CommonJS: const { verifyWebhook } = require("plasgos-crm-sdk/webhooks");

fastify.post("/webhooks/plasgos", { config: { rawBody: true } }, async (req, reply) => {
  try {
    const event = verifyWebhook(req.headers, req.rawBody, {
      expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN,
    });
    enqueue(event);
    reply.code(200).send();
  } catch {
    reply.code(401).send();
  }
});
```

### Webhook best practice

- Respond `2xx` fast, process asynchronously (10 s delivery timeout).
- Be idempotent — retries mean the same event can arrive more than once
  (dedupe on `message.id` + `event`).
- Always set a `verify_token`; without it anyone who knows the URL can post fakes.
- Tolerate new fields — `event_version` can increase.

---

## Utilities

Exported from the package root:

```js
import {
  computeSignature,   // (apiKey, secretKey, timestampSeconds) => hex string
  currentTimestamp,   // () => Unix seconds
  signRequest,        // (apiKey, secretKey, timestampSeconds?) => { "x-api-key", "x-timestamp", "x-signature" }
  BASE_URLS,          // { production, sandbox }
  resolveBaseUrl,     // ("sandbox" | "production" | url) => url
} from "plasgos-crm-sdk";
// CommonJS: const { computeSignature, signRequest } = require("plasgos-crm-sdk");

computeSignature("api_key", "secret_key", 1700000000);
// => "…64 hex chars…"
```

---

## Method reference

### `PlasgosCrmClient`

| Call | Returns |
|------|---------|
| `messages.send(input)` | `Envelope<Message> & { messageId }` |
| `messages.list()` | `Envelope<Message[]>` |
| `messages.get(messageId)` | `Envelope<Message>` |
| `accounts.list()` | `Envelope<Account[]>` |
| `accounts.get(accountId)` | `Envelope<Account>` |
| `references.variables()` | `Envelope<TemplateVariable[]>` |
| `references.contacts({ page?, limit?, search? })` | `Envelope<Record<string, unknown>[]>` |
| `official.accounts.list()` | `Envelope<Record<string, unknown>[]>` |
| `official.accounts.phoneNumbers(accountId)` | `Envelope<Record<string, unknown>[]>` |
| `official.templates.list(accountId, { category? })` | `Envelope<Record<string, unknown>[]>` |
| `official.messages.sendTemplate(input)` | `Envelope<Record<string, unknown>>` |
| `official.broadcasts.create(input)` | `Envelope<Record<string, unknown>>` (HTTP 201) |
| `official.broadcasts.list({ page?, limit?, status?, search? })` | `Envelope<Record<string, unknown>[]>` |
| `official.broadcasts.monitoring(broadcastId)` | `Envelope<Record<string, unknown>>` |
| `official.sharedWaba.sendOtp(serialToken, { to, otpCode?, metadata? })` | `Envelope<{ message_id, otp_code, serial_token }>` |
| `signatureHeaders(timestampSeconds?)` | `{ "x-api-key", "x-timestamp", "x-signature" }` |

### `PlasgosCrmCredentialsClient`

| Call | Returns |
|------|---------|
| `credentials.get()` | `Envelope<ApiCredential \| null>` |
| `credentials.request()` | `Envelope<ApiCredential>` |
| `credentials.regenerate()` | `Envelope<ApiCredential>` |
| `credentials.messages({ page?, limit? })` | `Envelope<Message[]>` |
| `credentials.subscribeWebhook({ webhookUrl, verifyToken? })` | `Envelope<Record<string, unknown>>` |

### `plasgos-crm-sdk/webhooks` (also on the root export)

| Export | |
|--------|-|
| `verifyWebhook(headers, rawBody, { expectedToken? })` | `WebhookEvent` (throws `WebhookVerificationError`) |
| `plasgosWebhook({ expectedToken? })` | Express middleware → `req.plasgosEvent` |
| `WebhookEventName` | `{ MESSAGE_RECEIVED, MESSAGE_STATUSES }` |
| Types | `WebhookEvent`, `WebhookEventBase`, `MessageReceivedEvent`, `MessageStatusesEvent`, `VerifyWebhookOptions` |

---

## Permissions

Your API key carries a `permissions` list; calling an endpoint outside it throws
`PermissionError` (403).

| Group | Scopes |
|-------|--------|
| Unofficial | `message:send`, `message:list`, `message:detail`, `account:list`, `account:detail` |
| Official | `official:message:send`, `official:account:list`, `official:phone-numbers:list`, `official:templates:list`, `official:broadcast:send`, `official:broadcast:list`, `official:broadcast:monitoring` |

`references.variables()` and `references.contacts()` need only a valid signature.

---

## TypeScript

Types ship with the package (no `@types/...` needed) for both ESM and CJS
resolution. Useful exported types:

```ts
import type {
  Envelope,
  Account,
  Message,
  TemplateVariable,
  ApiCredential,
  SendMessageInput,
  OfficialSendTemplateInput,
  OfficialBroadcastInput,
  Channel,
  ContentType,
  ClientOptions,
  CredentialsClientOptions,
} from "plasgos-crm-sdk";

import type {
  WebhookEvent,
  MessageReceivedEvent,
  MessageStatusesEvent,
} from "plasgos-crm-sdk/webhooks";
```

The `official.*` responses are typed loosely as `Record<string, unknown>` — read
`envelope.raw` or cast to your own interface.

---

## License

MIT — see [LICENSE](./LICENSE).

Full multi-language guide and the OpenAPI contract:
<https://github.com/plasgos/plasgos-crm-sdk>.
