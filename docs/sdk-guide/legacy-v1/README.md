# Plasgos CRM SDK — Panduan LEGACY v1 (`/api/v1`)

> **Usang (deprecated).** Panduan ini untuk klien v1 (`LegacyClient`, dulu `PlasgosCrmClient`) yang
> dihapus di SDK 3.0. Integrasi baru memakai **API Integrasi v3** — lihat [panduan utama](../README.md)
> dan [migrasi dari v1](../12-migrasi-dari-v1.md).

Dokumentasi penggunaan SDK resmi untuk fitur **API Integration** Plasgos CRM
(layanan `api-crm`). Tersedia untuk **Node.js / TypeScript**, **PHP**, dan
**Python** dengan permukaan API yang identik.

> Dokumen ini ditulis dalam Markdown standar dan aman diimpor ke Notion
> (import → Markdown). Setiap halaman berdiri sendiri; urutan baca mengikuti
> penomoran file.

---

## Daftar Isi

| # | Halaman | Isi |
|---|---------|-----|
| 1 | [Instalasi & Quick Start](./01-getting-started.md) | Pasang paket, contoh pertama, konsep inti |
| 2 | [Autentikasi](./02-authentication.md) | Signed API (HMAC), credential management (Bearer), cara memperoleh kredensial |
| 3 | [Konfigurasi Client](./03-configuration.md) | Semua opsi client, base URL, timeout, retry, transport kustom |
| 4 | [Response Envelope & Error](./04-responses-and-errors.md) | Bentuk `Envelope`, normalisasi, hierarki error, tabel mapping status |
| 5 | [Messages (WhatsApp Unofficial)](./05-messages.md) | Kirim teks/gambar/video/dokumen, list, detail |
| 6 | [Accounts & References](./06-accounts-and-references.md) | Akun terhubung, variabel template, kontak |
| 7 | [WhatsApp Official (WABA)](./07-official-whatsapp.md) | Akun, phone number, template, kirim template, broadcast, OTP shared WABA |
| 8 | [Credential Management](./08-credential-management.md) | Request/lihat/regenerate kredensial, daftar pesan, subscribe webhook |
| 9 | [Webhooks](./09-webhooks.md) | Event, struktur payload, verifikasi, integrasi framework |
| 10 | [Recipes & Best Practice](./10-recipes.md) | Idempotensi, pagination, bulk, retry, testing |

Referensi teknis tambahan:

- [`../../../openapi/openapi.yaml`](../../../openapi/openapi.yaml) — kontrak API (OpenAPI 3.1)
- [`../SDK_SPEC.md`](../../SDK_SPEC_LEGACY_V1.md) — kontrak method/field/error yang sama di 3 SDK
- [`../../PUBLISHING_NPM.md`](../../PUBLISHING_NPM.md) — registrasi & publish npm
- [`../../RELEASING.md`](../../RELEASING.md) — publish ke npm / Packagist / PyPI

---

## Paket

| Bahasa | Paket | Registry | Import |
|--------|-------|----------|--------|
| Node.js / TypeScript | `plasgos-crm-sdk` | npm | `import { PlasgosCrmClient } from "plasgos-crm-sdk"` |
| PHP | `plasgos/crm-sdk` | Packagist | `use Plasgos\CrmSdk\Client;` |
| Python | `plasgos-crm-sdk` | PyPI | `from plasgos_crm import PlasgosCrmClient` |

---

## Dua domain autentikasi

SDK ini menutup **dua permukaan API** yang mekanismenya berbeda:

| Domain | Prefix | Auth | Client SDK | Untuk siapa |
|--------|--------|------|-----------|-------------|
| **Signed API** | `/api/v1/*` | HMAC signature (`x-api-key` + `x-timestamp` + `x-signature`) | `PlasgosCrmClient` / `Client` | Integrator eksternal dengan API key yang sudah di-*approve* |
| **Credential management** | `/v2/integration/*` | Sesi user CRM (`Authorization: Bearer` + `fingerprint`) | `PlasgosCrmCredentialsClient` / `CredentialsClient` | Dashboard / aplikasi first-party untuk mengelola kredensial & webhook |

Detail lengkap: [Autentikasi](./02-authentication.md).

---

## Peta method

Semua method mengembalikan **`Envelope`** yang sudah dinormalisasi
(lihat [halaman 4](./04-responses-and-errors.md)) atau melempar error bertipe.

### Signed API — `PlasgosCrmClient`

| Namespace | Method | Endpoint | Permission |
|-----------|--------|----------|-----------|
| `messages` | `send` | `POST /api/v1/messages` | `message:send` |
| `messages` | `list` | `GET /api/v1/messages` | `message:list` |
| `messages` | `get` | `GET /api/v1/messages/{id}` | `message:detail` |
| `accounts` | `list` | `GET /api/v1/accounts` | `account:list` |
| `accounts` | `get` | `GET /api/v1/accounts/{id}` | `account:detail` |
| `references` | `variables` | `GET /api/v1/references/variables` | — (hanya signature) |
| `references` | `contacts` | `GET /api/v1/references/contacts` | — (butuh signature valid) |
| `official.accounts` | `list` | `GET /api/v1/official/accounts` | `official:account:list` |
| `official.accounts` | `phoneNumbers` | `GET /api/v1/official/accounts/phone-numbers/{id}` | `official:phone-numbers:list` |
| `official.templates` | `list` | `GET /api/v1/official/templates/{id}` | `official:templates:list` |
| `official.messages` | `sendTemplate` | `POST /api/v1/official/messages` | `official:message:send` |
| `official.broadcasts` | `create` | `POST /api/v1/official/broadcasts` | `official:broadcast:send` |
| `official.broadcasts` | `list` | `GET /api/v1/official/broadcasts` | `official:broadcast:list` |
| `official.broadcasts` | `monitoring` | `GET /api/v1/official/broadcasts/{id}/monitoring` | `official:broadcast:monitoring` |
| `official.sharedWaba` | `sendOtp` | `POST /api/v1/official/shared-waba/otp/{token}` | `official:message:send` |

### Credential management — `PlasgosCrmCredentialsClient`

| Method | Endpoint |
|--------|----------|
| `credentials.get` | `GET /v2/integration` |
| `credentials.request` | `POST /v2/integration` |
| `credentials.regenerate` | `POST /v2/integration/regenerate` |
| `credentials.messages` | `GET /v2/integration/messages` |
| `credentials.subscribeWebhook` | `POST /v2/integration/webhook` |

### Webhook helper

| Fungsi | Kegunaan |
|--------|----------|
| `verifyWebhook(headers, rawBody, { expectedToken })` | Verifikasi `x-hub-token` + parse payload |
| `plasgosWebhook({ expectedToken })` *(Node)* | Middleware Express |
| `Verifier::verify(...)` / `Verifier::verifyRequest(...)` *(PHP)* | Verifikasi array headers / PSR-7 request |

---

## Konvensi penamaan

| | SDK | Wire (HTTP) |
|-|-----|-------------|
| Node / PHP | `camelCase` (mis. `phoneNumber`, `accountId`) | `snake_case` (`phone_number`, `account_id`) — SDK yang memetakan |
| Python | `snake_case` argumen | `snake_case` — sama |

Pengecualian penting: nilai `channel` **`"messanger"`** memang salah eja di
server dan wajib dipakai apa adanya.

---

## Lingkungan

| Nama | Base URL |
|------|----------|
| `production` | `https://wa-client.plasgos.co.id` |
| `sandbox` | `https://api-crm.sandbox.plasgos.co.id` |

Semua contoh di panduan ini pakai `sandbox`. Ganti ke `production` saat siap.

➡️ Lanjut: [1. Instalasi & Quick Start](./01-getting-started.md)
