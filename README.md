# plasgos-crm-sdk

Official SDKs for the **Plasgos CRM — Api Integration** API (the `api-crm`
service). One contract, three languages.

| Language | Package | Registry | Path |
|---|---|---|---|
| Node.js / TypeScript | `plasgos-crm-sdk` | npm | [`packages/node`](packages/node) |
| PHP | `plasgos/crm-sdk` | Packagist | [`packages/php`](packages/php) |
| Python | `plasgos-crm-sdk` | PyPI | [`packages/python`](packages/python) |

## Documentation

**[docs/sdk-guide/](docs/sdk-guide/README.md)** — full usage guide (Bahasa
Indonesia), from authentication through every endpoint and webhooks, with
side-by-side Node / PHP / Python examples. Written as plain Markdown, safe to
import into Notion.

## What the SDKs cover

> **SDK 2.0 = API Integrasi v3** (`/api/v3`): omnichannel messages, contacts, leads, broadcasts,
> followups, HMAC-signed webhooks. PHP, Node and Python 2.0 are implemented
> ([UPGRADING.md](UPGRADING.md)) against one contract ([docs/SDK_SPEC.md](docs/SDK_SPEC.md)). The
> section below describes the **legacy v1** surface (`LegacyClient` in 2.x, `Client` in 1.x).

- **Signed API** (`/api/v1/*`) — HMAC-signed requests for approved API keys:
  - `messages` — send / list / get unofficial WhatsApp messages
  - `accounts` — list / get connected accounts
  - `references` — template variables, contacts
  - `official.accounts` / `official.templates` / `official.messages` /
    `official.broadcasts` / `official.sharedWaba` — WhatsApp Business API
- **Credential management** (`/v2/integration/*`) — user-session auth:
  request / inspect / regenerate credentials, register a webhook
- **Webhooks** — verify `message.received` / `message.statuses` deliveries

## Source of truth

1. [`openapi/openapi-v3.json`](openapi/openapi-v3.json): the API v3 contract. It is a copy of
   `api-crm`'s `server/modules/api_integration/docs/openapi/integration-v3.openapi.json`.
2. [`docs/SDK_SPEC.md`](docs/SDK_SPEC.md): the shared method surface, signing, error mapping and
   retry policy that every SDK implements identically.
3. [`docs/vectors-v3.json`](docs/vectors-v3.json): golden request and webhook signature vectors.

Legacy v1: [`openapi/openapi.yaml`](openapi/openapi.yaml),
[`docs/SDK_SPEC_LEGACY_V1.md`](docs/SDK_SPEC_LEGACY_V1.md), [`docs/vectors.json`](docs/vectors.json).

To change the API, update `openapi-v3.json`, then `SDK_SPEC.md`, then the three implementations.

## Authentication in one paragraph

API v3: send `Plasgos-Api-Key`, `Plasgos-Timestamp` (Unix seconds) and `Plasgos-Signature`, where
`Plasgos-Signature = "v1=" + hex(HMAC_SHA256(secret, timestamp \n METHOD \n path?query \n hex(sha256(body))))`.
Method, path, query and body are all signed. Webhooks carry `Plasgos-Webhook-Signature` (HMAC over
`id.timestamp.body`).

Legacy v1 signed API: send `x-api-key`, `x-timestamp` (Unix seconds) and
`x-signature = lowercase_hex(HMAC_SHA256(secret_key, "{api_key}:{timestamp}"))`.
The timestamp must be within 300 s of server time. Only `api_key:timestamp` is
signed — not the method, path or body.

Credential management: `Authorization: Bearer <access token>` + a `fingerprint`
header from the CRM session.

Webhooks are **not** HMAC-signed; they carry your `verify_token` in
`x-hub-token`.

## Repository layout

```
openapi/openapi.yaml         API contract (OpenAPI 3.1)
docs/sdk-guide/              full usage guide (10 pages, Notion-ready)
docs/SDK_SPEC.md             shared SDK contract
docs/vectors.json            golden signature vectors
docs/RELEASING.md            per-registry publish steps
docs/PUBLISHING_NPM.md       npm registration + publish walkthrough
packages/node                plasgos-crm-sdk
packages/php                 plasgos/crm-sdk
packages/python              plasgos-crm-sdk
.github/workflows            CI (lint + test per package)
```

## Development

Each package is self-contained:

```bash
# Node
cd packages/node && npm install && npm test && npm run build

# PHP
cd packages/php && composer install && composer test

# Python
cd packages/python && pip install -e ".[dev]" && pytest
```

## License

MIT — see [LICENSE](LICENSE).
