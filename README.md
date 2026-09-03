# plasgos-crm-sdk

Official SDKs for the **Plasgos CRM — Api Integration** API (the `api-crm`
service). One contract, three languages.

| Language | Package | Registry | Path |
|---|---|---|---|
| Node.js / TypeScript | `plasgos-crm-sdk` | npm | [`packages/node`](packages/node) |
| PHP | `plasgos/crm-sdk` | Packagist | [`packages/php`](packages/php) |
| Python | `plasgos-crm-sdk` | PyPI | [`packages/python`](packages/python) |

## What the SDKs cover

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

1. [`openapi/openapi.yaml`](openapi/openapi.yaml) — the full API contract.
2. [`docs/SDK_SPEC.md`](docs/SDK_SPEC.md) — the shared method surface, field
   mapping and error mapping every SDK implements identically.
3. [`docs/vectors.json`](docs/vectors.json) — golden HMAC signature vectors used
   by all three test suites.

Change the API → update `openapi.yaml`, then `SDK_SPEC.md`, then the three
implementations.

## Authentication in one paragraph

Signed API: send `x-api-key`, `x-timestamp` (Unix seconds) and
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
