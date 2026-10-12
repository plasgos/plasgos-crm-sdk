# Changelog

All notable changes to this package are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

### Added
- `Client` for **API Integrasi v3** (`/api/v3`).
  - Resources: `me`, `channels`, `endpoints` (including templates), `templateVariables`, `messages`
    (omnichannel), `contacts` (including upsert and lookup), `contactGroups`, `pipelines`, `leads`,
    `broadcasts`, `followups` (enrol and unenrol), `webhooks`.
  - Request signing covers the method, path, query and body (`Plasgos-*` headers).
  - An automatic `Idempotency-Key` on POST, reused across retries.
  - `Response` and `Page` (`autoPaging()`) types.
  - `Content` and `TemplateContent` builders.
  - `Webhook\Webhook` HMAC verification with secret rotation, plus `Webhook\EventType`.
  - Shared golden vectors in `docs/vectors-v3.json`.
- `ChannelException` (502) and `ServiceUnavailableException` (503), both `ServerException`.
- v3 error fields on every exception: `errorCode`, `errorType`, `details`, `fields`, `docUrl` and
  `retryAfter`.
- `ClientConfig::$maxRetryWait`, and the `sleeper` and `clock` test hooks.

### Aligned with the server (API v3 phase V2)
- `TemplateContent::location()` for templates with a LOCATION header. There is no header-media
  override: media headers always use the approved template's media.
- `messages->sendOptions()` accepts `endpoint_id` (Instagram/Messenger identities).
- `messages->list()` is not part of v3.0; it returns when the server ships `GET /messages`.
- Message objects carry `status` + `timestamp`; there are no per-status timestamps in v3.0.

### Changed (breaking)
- The v1 client is renamed to `LegacyClient`, and its types moved to `Plasgos\CrmSdk\Legacy\*`.
  See UPGRADING.md.
- `SignatureExpiredException` now extends `AuthenticationException`.
- The default `User-Agent` now includes the PHP version.

### Deprecated
- `LegacyClient` and the `Legacy\*` namespace. They will be removed in 3.0.

## [1.0.0] - 2026-09-03

### Added
- Initial release: signed API client, credential-management client, webhook
  verification, typed errors, normalised response envelope.
