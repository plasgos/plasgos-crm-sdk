# Plasgos CRM SDK — Panduan Lengkap (API Integrasi v3)

Panduan memakai SDK resmi **2.x** untuk **API Integrasi v3** Plasgos CRM (`/api/v3`). Tersedia untuk
**Node.js / TypeScript**, **PHP**, dan **Python** dengan permukaan yang sama: nama metode
`camelCase` di Node & PHP, `snake_case` di Python; isi permintaan & respons memakai format kabel API
(`snake_case`) tanpa diubah.

> Ditulis dalam Markdown standar dan aman diimpor ke Notion (import → Markdown). Setiap halaman berdiri
> sendiri; urutan baca mengikuti penomoran.
>
> Masih memakai `/api/v1` (`PlasgosCrmClient` / `LegacyClient`)? Panduannya dipindah ke
> [legacy-v1/](./legacy-v1/README.md), dan cara pindah ada di [Migrasi dari v1](./12-migrasi-dari-v1.md).

---

## Daftar Isi

| # | Halaman | Isi |
|---|---------|-----|
| 1 | [Mulai](./01-mulai.md) | Pasang paket, panggilan pertama, konsep inti |
| 2 | [Autentikasi & scope](./02-autentikasi.md) | Kunci API, tanda tangan v3, scope, jam server |
| 3 | [Konfigurasi klien](./03-konfigurasi.md) | Base URL, timeout, coba ulang, transport kustom |
| 4 | [Respons & galat](./04-respons-dan-galat.md) | `ApiResponse`, `Page`, hierarki galat, kode galat, kebijakan coba ulang |
| 5 | [Pesan](./05-pesan.md) | Cek penerima, kirim teks/media/template, baca status pesan |
| 6 | [Kontak](./06-kontak.md) | Daftar, cari, buat, upsert, ubah, grup kontak |
| 7 | [Pipeline & lead](./07-pipeline-dan-lead.md) | Pipeline & stage, buat lead |
| 8 | [Broadcast](./08-broadcast.md) | Broadcast template WhatsApp resmi, status penerima, batalkan |
| 9 | [Followup](./09-followup.md) | Daftarkan & keluarkan kontak dari urutan followup |
| 10 | [Webhook](./10-webhook.md) | Langganan event, verifikasi tanda tangan, rotasi secret |
| 11 | [Resep](./11-resep.md) | Toko online, formulir, sinkron bertahap, pengujian |
| 12 | [Migrasi dari v1](./12-migrasi-dari-v1.md) | Padanan klien, metode, respons, webhook |

Referensi teknis:

- [`../../openapi/openapi-v3.json`](../../openapi/openapi-v3.json) — kontrak API v3 (OpenAPI)
- [`../SDK_SPEC.md`](../SDK_SPEC.md) — kontrak metode, tanda tangan, galat & coba ulang yang sama di 3 SDK
- [`../../UPGRADING.md`](../../UPGRADING.md) — perubahan 1.x → 2.0 per bahasa
- [`../vectors-v3.json`](../vectors-v3.json) — vektor uji tanda tangan permintaan & webhook

---

## Paket

| Bahasa | Paket | Registry | Import |
|--------|-------|----------|--------|
| Node.js / TypeScript | `plasgos-crm-sdk` | npm | `import { Client, Content } from "plasgos-crm-sdk"` |
| PHP (8.1+) | `plasgos/crm-sdk` | Packagist | `use Plasgos\CrmSdk\Client;` |
| Python (3.9+) | `plasgos-crm-sdk` | PyPI | `from plasgos_crm import Client, Content` |

## Peta resource

| Resource | Node / PHP | Python | Scope |
|---|---|---|---|
| Akun & kuota | `me` | `me` | `account:read` |
| Channel & titik kirim | `channels`, `endpoints`, `templateVariables` | `channels`, `endpoints`, `template_variables` | `channels:read` |
| Pesan | `messages` | `messages` | `messages:read` / `messages:write` |
| Kontak | `contacts`, `contactGroups` | `contacts`, `contact_groups` | `contacts:read` / `contacts:write` |
| Pipeline & lead | `pipelines`, `leads` | `pipelines`, `leads` | `leads:read` / `leads:write` |
| Broadcast | `broadcasts` | `broadcasts` | `broadcasts:read` / `broadcasts:write` |
| Followup | `followups` | `followups` | `followups:read` / `followups:write` |
| Webhook | `webhooks` | `webhooks` | `webhooks:manage` |

Endpoint yang belum punya metode tetap bisa dipanggil lewat `client.request(method, path, …)`
(lihat [Konfigurasi klien](./03-konfigurasi.md)).
