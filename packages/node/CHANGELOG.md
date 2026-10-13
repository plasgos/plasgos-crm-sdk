# Changelog

All notable changes to this package are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

### API contract 3.0.0-rc.2
- One `contact` object names "who" in `messages.send`, `leads.create` and `followups.enroll`: `{id}` | `{phone_number, …profile}` | `{channel, external_id}` | `{email}` (replaces `recipient` + `identity` + `create_contact`, and `contact_id` | `contact`). Profile fields only fill a NEW contact. `send-options` / `lookup` queries use the same keys (`send-options` also accepts `email`).
- `meta.created` everywhere (contact upsert: replaces `meta.operation`; message send: `true`).
- Malformed `contact` → `422 validation_failed` with field code `invalid_contact`. Error catalog: `components.x-error-codes` in `openapi/openapi-v3.json`.

### Fixed
- `409 idempotency_in_progress` is retried with the same Idempotency-Key (the first request is still running, e.g. after a client-side timeout); other 409s such as `identity_taken` are still raised.

### Added
- Types generated from `openapi/openapi-v3.json` (`scripts/generate-types.mjs`, checked in CI): `export type * as Types` with every schema and `Types.Operations[operationId]` (`query`, `body`, `data`, `meta`, `item`); every resource method is typed (`ApiResponse<data, meta>`, `Page<item>`), `content` also accepts the `TemplateContent` builder.
- `Client` for **API Integrasi v3** (`/api/v3`), same contract as the PHP SDK 2.0 (docs/SDK_SPEC.md):
  - Resources: `me`, `channels`, `endpoints` (including templates), `templateVariables`, `messages`
    (omnichannel), `contacts` (including upsert and lookup), `contactGroups`, `pipelines`, `leads`,
    `broadcasts`, `followups` (enrol and unenrol), `webhooks`, plus `client.request()`.
  - Request signing covers the method, path, query and body (`Plasgos-*` headers); exported as
    `signV3`, `signatureHeadersV3`, `stringToSign`.
  - An automatic `Idempotency-Key` on POST, reused across retries; retry policy of §6
    (`maxRetries`, `maxRetryWait`, injectable `sleep`/`clock`).
  - `ApiResponse` and `Page` (`autoPaging()` async iterator).
  - `Content` / `TemplateContent` builders (`location()` for LOCATION-header templates).
  - `Webhook.verify()` (HMAC, secret rotation with two signatures) and `WebhookEventType`.
- `ChannelError` (502) and `ServiceUnavailableError` (503), both `ServerError`.
- v3 error fields on every error: `errorCode`, `errorType`, `details`, `fields`, `docUrl`, `retryAfter`;
  `errorFromV3()`.
- Optional live test (`test/live.test.ts`, `PLASGOS_LIVE_*`).

### Changed (breaking)
- The v1 client is now `LegacyClient` (`PlasgosCrmClient` stays as a deprecated alias until 3.0);
  its documentation moved to `LEGACY_V1.md`.
- `SignatureExpiredError` now extends `AuthenticationError` (v3 reports clock skew as 401).
- The legacy `x-hub-token` helpers `verifyWebhook` / `plasgosWebhook` are deprecated
  (aliases `verifyLegacyWebhook` / `legacyWebhookMiddleware`); v3 deliveries use `Webhook.verify()`.

## [1.0.2] - 2026-09-03

First release published to npm. `1.0.0` and `1.0.1` were unpublished
pre-release iterations; their notes are folded in below.

## [1.0.1] - 2026-09-03

### Changed
- Package renamed to the unscoped name `plasgos-crm-sdk`
  (`npm install plasgos-crm-sdk`).
- Added `publishConfig` (`access: public`) and a tag-triggered release workflow.
- Hardened dual-format packaging: per-condition `exports` for ESM (`import` →
  `dist/index.js`) and CommonJS (`require` → `dist/index.cjs`), with matching
  `.d.ts` / `.d.cts` type declarations for `node16`/`nodenext`/`bundler`
  resolution; added `sideEffects: false` and a `smoke` script that installs the
  packed tarball and verifies both `require()` and `import` (also run in CI).

## [1.0.0] - 2026-09-03

### Added
- Initial release: signed API client, credential-management client, webhook
  verification, typed errors, normalised response envelope.
