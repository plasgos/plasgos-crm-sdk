# Changelog

All notable changes to this package are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

### Added
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
