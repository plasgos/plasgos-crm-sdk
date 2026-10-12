# plasgos-crm-sdk (Node.js / TypeScript)

Official Node SDK for **Plasgos CRM: API Integrasi v3**. The SDK covers:

- omnichannel messages (WhatsApp official and unofficial, Instagram, Messenger)
- contacts and leads
- broadcasts
- followups
- signed webhooks

Features:

- Node 18+, ESM and CommonJS, full TypeScript types. Uses the global `fetch` (injectable).
- Automatic request signing that covers the method, path, query and body.
- An automatic `Idempotency-Key` on every POST, reused on retries.
- Typed errors carrying the API `errorCode`.
- A `Page` type with `autoPaging()`.
- Webhook HMAC verification.

> Upgrading from 1.x? See [UPGRADING.md](../../UPGRADING.md). The v1 client lives on as the
> deprecated `LegacyClient` (`PlasgosCrmClient` is an alias); its docs are in [LEGACY_V1.md](LEGACY_V1.md).

## Install

```bash
npm install plasgos-crm-sdk
```

## Quick start

```ts
import { Client, Content, PlasgosApiError } from "plasgos-crm-sdk";
// CommonJS: const { Client, Content, PlasgosApiError } = require("plasgos-crm-sdk");

const client = new Client(process.env.PLASGOS_API_KEY!, process.env.PLASGOS_SECRET_KEY!, {
  baseUrl: "sandbox", // "production" by default; or an absolute URL
});

const me = await client.me.get();
console.log(me.data.tenant.name);

try {
  const sent = await client.messages.send(
    {
      endpoint_id: "6281100000001",
      recipient: { phone_number: "081234567890" },
      create_contact: { name: "Budi Santoso" },
      content: Content.template("66f1a2b3c4d5e6f708192a3b").dynamic("name", "{{full_name}}").custom("order", "INV-2026-0917"),
    },
    "wc-1234-processing", // Idempotency-Key: one per intent
  );
  console.log(sent.data.status); // pending | sent | failed (202)
} catch (error) {
  // Branch on the stable code; show error.message (Indonesian) to people.
  if (error instanceof PlasgosApiError && error.errorCode === "requires_template") { /* ... */ }
  throw error;
}

for await (const contact of (await client.contacts.list({ per_page: 100 })).autoPaging()) {
  // every page fetched lazily
}

await client.contacts.upsert({ phone_number: "081234567890", name: "Budi", tags: ["woocommerce"] });
await client.leads.create({ contact: { phone_number: "081234567890" }, pipeline_id: "pl_1" });
await client.followups.enroll("fu_1", { contact_id: "ct_1" });
```

Resources:

- `me`, `channels`
- `endpoints` (including `templates`), `templateVariables`
- `messages`
- `contacts`, `contactGroups`
- `pipelines`, `leads`
- `broadcasts`, `followups`
- `webhooks`

`client.request(method, path, query?, body?, idempotencyKey?)` is an escape hatch for endpoints that
do not have a method yet. The full surface is in [docs/SDK_SPEC.md](../../docs/SDK_SPEC.md) §3.
Inputs and outputs use the API wire format (snake_case), described by
[openapi/openapi-v3.json](../../openapi/openapi-v3.json).

## Options

| Option | Default | Meaning |
|---|---|---|
| `baseUrl` | `production` | `production`, `sandbox`, or an absolute URL (host only). |
| `timeout` | `15000` ms | Per-request timeout. |
| `maxRetries` | `2` | Automatic retries (see below). |
| `maxRetryWait` | `30` s | Longest `Retry-After` honoured automatically. |
| `userAgent` | `plasgos-crm-sdk-node/<version> node/<version>` | Integrations append themselves. |
| `fetch` | global `fetch` | Injectable transport. |
| `sleep`, `clock` | real | Test hooks. |

## Responses

```ts
r.httpStatus; // 200, 201, 202 …
r.message;    // user-facing text
r.data;       // payload
r.meta;       // e.g. { operation: "created" } for upserts
r.requestId;  // quote it when contacting Plasgos
r.rateLimit;  // { limit: 120, remaining: 119, reset: 42 }
r.replayed;   // true when the server replayed the first response for this Idempotency-Key
```

List methods return a `Page`: `items`, `pagination`, `hasNextPage()`, `nextPage()`, `autoPaging()`
(async iterator), and it is iterable over the current page.

## Errors

```
PlasgosApiError
├─ AuthenticationError            401
│  └─ SignatureExpiredError       401 signature_expired (check the server clock)
├─ PermissionError                403 (scope_missing, api_key_revoked, …)
│  └─ PlanError                   403 not_entitled
├─ NotFoundError                  404
├─ ConflictError                  409 (idempotency_in_progress, identity_taken, …)
├─ BadRequestError                400
├─ ValidationError                422 / 400 with fields → .fields
├─ RateLimitError                 429 → .retryAfter (rate_limited | daily_quota_exceeded)
├─ ServerError                    5xx
│  ├─ ChannelError                502 provider error
│  └─ ServiceUnavailableError     503
└─ TransportError                 no response
```

Every error exposes `errorCode`, `errorType`, `details`, `fields`, `docUrl`, `requestId` and
`retryAfter`.

**Retries.** Transport errors, 502/503 and `rate_limited` (for any method) are retried
automatically up to `maxRetries`, reusing the same idempotency key. A plain 500 is retried only for
GET. Other 4xx errors and `daily_quota_exceeded` are never retried.

## Webhooks

```ts
import { Webhook, WebhookEventType } from "plasgos-crm-sdk"; // also "plasgos-crm-sdk/webhooks"

app.post("/webhooks/plasgos", express.text({ type: () => true }), (req, res) => {
  const event = Webhook.verify(req.body, req.headers, signingSecret);
  // during secret rotation: Webhook.verify(body, headers, [newSecret, previousSecret])
  if (event.type === WebhookEventType.MESSAGE_RECEIVED) { /* event.data is a Message */ }
  res.sendStatus(200);
});
```

Verify the **raw** body. Deliveries can repeat, so drop `event.id` values you have already processed.
