# 12. Migrasi dari v1

SDK 2.0 menarget API v3. Klien v1 tetap tersedia sebagai `LegacyClient` (usang, dihapus di 3.0) —
panduannya di [legacy-v1/](./legacy-v1/README.md). Rincian per bahasa: [`../../UPGRADING.md`](../../UPGRADING.md).

## Kunci & tanda tangan

Kunci API yang sama dipakai di v3 — tak perlu kunci baru. Izin v1 diterjemahkan otomatis ke scope v3;
scope baru (kontak, lead, followup, webhook) diminta ke admin (lihat [Autentikasi](./02-autentikasi.md)).
Tanda tangan v3 berbeda (menutup metode, path, query, dan body) — SDK mengurusnya.

## Padanan

| v1 (`LegacyClient`) | v3 (`Client`) |
|---|---|
| `new PlasgosCrmClient(key, secret, …)` | `new Client(key, secret, …)` (Python: `Client(key, secret, …)`) |
| `messages.send({ channel, account_id, receiver, content })` | `messages.send({ endpoint_id, recipient, content }, idempotencyKey)` |
| `official.messages.sendTemplate(...)` | `messages.send({ …, content: Content.template(id)… })` |
| `official.broadcasts.create(...)` | `broadcasts.create({ name, endpoint_id, content, audience })` |
| `accounts.list()` / `official.accounts.list()` | `endpoints.list()` (semua channel dalam satu daftar) |
| `official.templates.list(...)` | `endpoints.templates(endpointId)` |
| `references.variables()` | `templateVariables.list()` |
| `references.contacts(...)` | `contacts.list(...)`, `contacts.lookup(...)` |
| — | `contacts.upsert`, `leads.create`, `followups.enroll`, `webhooks.*` (baru) |
| Amplop `{ status, message, data, metadata }` | `ApiResponse { httpStatus, message, data, meta, requestId, rateLimit, replayed }` |
| Daftar berhalaman manual | `Page` + `autoPaging()` |
| `SignatureExpiredError` (408) | tetap ada, kini turunan `AuthenticationError` (v3: 401 `signature_expired`) |
| Webhook `x-hub-token` + `verifyWebhook()` | `Webhook.verify()` dengan HMAC `Plasgos-Webhook-Signature` |

## Langkah aman

1. Pasang SDK 2.x; kode lama tetap jalan dengan mengganti `PlasgosCrmClient` → `LegacyClient`
   (nama lama masih alias; Python memberi `DeprecationWarning`).
2. Pindahkan fitur satu per satu ke `Client` v3, mulai dari kirim pesan.
3. Daftarkan webhook v3 (`webhooks.create`) dan verifikasi dengan `Webhook.verify`; webhook v1 tetap
   berjalan sampai v1 dipensiunkan.
4. Hapus pemakaian `LegacyClient` sebelum naik ke SDK 3.0.
