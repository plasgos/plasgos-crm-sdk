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
- An empty `custom_fields` (PHP decodes `{}` as `[]`) is sent as a JSON object, so `contacts->update($id, ['custom_fields' => $contact->data['custom_fields']])` no longer fails with 422.

### Added
- Per-call options (`RequestOptions`, docs/SDK_SPEC.md §1.1) as the last argument of every resource method and `$client->request()`: `timeout` (default Guzzle transport; a custom PSR-18 client gets a `LogicException`), `maxRetries`, `headers`. Next pages of `autoPaging()` keep them. Headers the SDK owns (signing, `Idempotency-Key`, `Content-Type`, …) are rejected.
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

### Aligned with the server (API v3 phase V3)
- Duplicate phone/email is `identity_taken` (409, `details['contact_id']`), not `contact_exists`.
- `contacts->lookup()` accepts `endpoint_id`; merged contacts resolve to their target (`meta['redirected_from']`).
- `leads->create()`: whether a lead was created is `meta['created']` (plus `meta['contact_created']`);
  `data` is always the lead. One active lead per contact across all pipelines.

### Aligned with the server (API v3 phase V4)
- `broadcasts->create()`: official WhatsApp templates only in v3.0; audience max 1000 recipients;
  no per-recipient variables and no `sending` options.
- `followups->enroll()`: no per-enrollment `variables` in v3.0; `meta['created']` tells whether it
  was a new enrollment. `unenroll()` of a never-enrolled contact → `enrollment_not_found`.

### Aligned with the server (API v3 phase V5)
- Webhook deliveries: 8 retries over ~24 h with the same `Plasgos-Webhook-Id` and body; subscriptions
  failing for 3 days are disabled (`disabled_reason: too_many_failures`, re-enable with `update(['enabled' => true])`).
- During the 24 h after `rotateSecret()` the signature header carries two `v1=` values.

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
