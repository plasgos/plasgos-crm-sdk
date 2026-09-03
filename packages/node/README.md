# plasgos-crm-sdk

Official Node.js / TypeScript SDK for the **Plasgos CRM — Api Integration** API.

- Zero runtime dependencies (Node 18+ `fetch` + `node:crypto`).
- Automatic HMAC request signing.
- Typed models, typed errors, normalised response envelope.
- Webhook verification helper (+ Express middleware).
- Separate client for the credential-management endpoints.

## Install

```bash
npm install plasgos-crm-sdk
```

## Quick start (signed API)

```ts
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const client = new PlasgosCrmClient(
  process.env.PLASGOS_API_KEY!,
  process.env.PLASGOS_SECRET_KEY!,
  { baseUrl: "production" }, // "production" | "sandbox" | absolute URL
);

// Send an unofficial WhatsApp message
const res = await client.messages.send({
  channel: "whatsapp",
  account: { accountId: "665f1c2e9a1b2c3d4e5f6a7b" },
  receiver: { phoneNumber: "628123456789", name: "John Doe" },
  content: { type: "text", data: { text: "Halo dari SDK" } },
});
console.log(res.messageId, res.data);

// List connected accounts
const accounts = await client.accounts.list();

// WABA official template message
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
```

Every call resolves to a normalised envelope:

```ts
interface Envelope<T> {
  status: number | boolean;
  message: string | null;
  data: T;
  metadata: Record<string, unknown>;
  raw: unknown;
  httpStatus: number;
}
```

## Errors

```ts
import { RateLimitError, ValidationError, SignatureExpiredError } from "plasgos-crm-sdk";

try {
  await client.messages.send(/* ... */);
} catch (err) {
  if (err instanceof ValidationError) console.error(err.errors);
  else if (err instanceof RateLimitError) console.error("slow down");
  else if (err instanceof SignatureExpiredError) console.error("check server clock");
  else throw err;
}
```

Hierarchy: `PlasgosApiError` → `AuthenticationError`, `PermissionError`
(`PlanError`), `NotFoundError`, `SignatureExpiredError`, `ConflictError`,
`ValidationError`, `BadRequestError`, `RateLimitError`, `ServerError`,
`TransportError`.

> Note: an un-approved API key can surface as HTTP 500 (`ServerError`) with a
> message containing `"You don't have permission"` — this is a known server quirk.

## Credential management (`/v2/integration/*`)

These endpoints use the CRM **user session** auth, not signing:

```ts
import { PlasgosCrmCredentialsClient } from "plasgos-crm-sdk";

const creds = new PlasgosCrmCredentialsClient(accessToken, fingerprint, {
  baseUrl: "production",
});

await creds.credentials.request();        // request API access
const current = await creds.credentials.get();
await creds.credentials.regenerate();      // rotate api_key + secret_key
await creds.credentials.subscribeWebhook({
  webhookUrl: "https://your.app/webhooks/plasgos",
  verifyToken: "a-shared-secret",
});
```

## Webhooks

Plasgos CRM pushes `message.received` and `message.statuses` events to your
registered URL, with your `verify_token` in the `x-hub-token` header. There is
**no** payload HMAC.

```ts
import { verifyWebhook, WebhookEventName } from "plasgos-crm-sdk/webhooks";

const event = verifyWebhook(req.headers, rawBody, {
  expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN,
});

if (event.event === WebhookEventName.MESSAGE_RECEIVED) {
  // handle inbound message
}
```

Express:

```ts
import express from "express";
import { plasgosWebhook } from "plasgos-crm-sdk/webhooks";

app.post(
  "/webhooks/plasgos",
  express.text({ type: () => true }),
  plasgosWebhook({ expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN }),
  (req, res) => {
    console.log((req as any).plasgosEvent);
    res.sendStatus(200);
  },
);
```

## Configuration

| Option | Default | |
|---|---|---|
| `baseUrl` | `"production"` | `"production"`, `"sandbox"`, or an absolute URL |
| `timeout` | `15000` | ms |
| `maxRetries` | `2` | idempotent GET only; network / 5xx / 429 |
| `userAgent` | `plasgos-crm-sdk-node/<version>` | |
| `fetch` | `globalThis.fetch` | inject for tests / custom runtimes |

## Development

```bash
npm install
npm run typecheck
npm test
npm run build
```
