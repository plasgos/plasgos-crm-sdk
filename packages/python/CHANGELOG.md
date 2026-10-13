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
- Lint rules pinned explicitly (`[tool.ruff.lint] select`): newer ruff versions widened the default rule set and broke CI without any code change.

### Added
- Per-call options (`options=RequestOptions`, docs/SDK_SPEC.md §1.1), keyword-only on every resource method and `client.request()`: `timeout`, `max_retries`, `headers`. Next pages of `auto_paging()` keep them. Headers the SDK owns (signing, `Idempotency-Key`, `Content-Type`, …) are rejected.
- Types generated from `openapi/openapi-v3.json` (`scripts/generate-types.mjs`, checked in CI): `plasgos_crm.v3.types` (`TypedDict` per schema, plus `…Body`, `…Query`, `…Data`, `…Item`, `…Meta` per operation); `ApiResponse` is generic (`ApiResponse[Contact]`), every resource method is typed, and `Content.text()` / `media()` / `TemplateContent.to_dict()` return the matching `TypedDict`.
- `Client` for **API Integrasi v3** (`/api/v3`), same contract as the PHP and Node SDK 2.0
  (docs/SDK_SPEC.md):
  - Resources: `me`, `channels`, `endpoints` (including templates), `template_variables`,
    `messages` (omnichannel, `send_options`), `contacts` (including upsert and lookup),
    `contact_groups`, `pipelines`, `leads`, `broadcasts`, `followups` (enrol and unenrol),
    `webhooks` (including `rotate_secret`), plus `client.request()`.
  - Request signing covers the method, path, query and body (`Plasgos-*` headers); exported as
    `sign_v3`, `signature_headers_v3`, `string_to_sign`.
  - An automatic `Idempotency-Key` on POST, reused across retries; retry policy of §6
    (`max_retries`, `max_retry_wait`, injectable `sleeper`/`clock`).
  - `ApiResponse`, `RateLimit` and `Page` (`auto_paging()` generator).
  - `Content` / `TemplateContent` builders (`location()` for LOCATION-header templates).
  - `Webhook.verify()` (HMAC, secret rotation with two signatures; any header mapping including
    WSGI `environ`) and `WebhookEventType`.
- `ChannelError` (502) and `ServiceUnavailableError` (503), both `ServerError`.
- v3 error fields on every error: `error_code`, `error_type`, `details`, `fields`, `doc_url`,
  `retry_after`; `error_from_v3()`.
- Optional live test (`tests/test_v3_live.py`, `PLASGOS_LIVE_*`).

### Changed (breaking)
- The v1 client is now `LegacyClient` (`PlasgosCrmClient` stays as a deprecated alias until 3.0)
  and emits a `DeprecationWarning` when constructed; its documentation moved to `LEGACY_V1.md`.
- `SignatureExpiredError` now extends `AuthenticationError` (v3 reports clock skew as 401).
- The legacy `x-hub-token` helper `verify_webhook` is v1-only; v3 deliveries use `Webhook.verify()`.

## [1.0.0] - 2026-09-03

### Added
- Initial release: signed API client, credential-management client, webhook
  verification, typed errors, normalised response envelope.
