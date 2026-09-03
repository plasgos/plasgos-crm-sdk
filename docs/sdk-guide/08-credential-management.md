# 8. Credential Management

[← WhatsApp Official](./07-official-whatsapp.md) · [Webhooks →](./09-webhooks.md)

---

Endpoint `/v2/integration/*` untuk mengelola kredensial API dan mendaftarkan
webhook. **Auth-nya berbeda** dari signed API — memakai sesi user CRM:

| Header | Nilai |
|--------|-------|
| `Authorization` | `Bearer <access token terenkripsi>` |
| `fingerprint` | Fingerprint device terikat sesi |

Client SDK: `PlasgosCrmCredentialsClient` (Node/Python) /
`CredentialsClient` (PHP). Lihat [Autentikasi §2.4](./02-authentication.md#24-credential-management--bearer--fingerprint).

| Method | Endpoint |
|--------|----------|
| `credentials.get()` | `GET /v2/integration` |
| `credentials.request()` | `POST /v2/integration` |
| `credentials.regenerate()` | `POST /v2/integration/regenerate` |
| `credentials.messages({ page?, limit? })` | `GET /v2/integration/messages` |
| `credentials.subscribeWebhook({ webhookUrl, verifyToken? })` | `POST /v2/integration/webhook` |

> Python: `credentials.subscribe_webhook(webhook_url=..., verify_token=...)`.

---

## 8.1 Membuat client

```ts
// Node
import { PlasgosCrmCredentialsClient } from "plasgos-crm-sdk";
const creds = new PlasgosCrmCredentialsClient(accessToken, fingerprint, {
  baseUrl: "production",
});
```

```php
// PHP
use Plasgos\CrmSdk\CredentialsClient;
$creds = new CredentialsClient($accessToken, $fingerprint);
```

```python
# Python
from plasgos_crm import PlasgosCrmCredentialsClient
creds = PlasgosCrmCredentialsClient(access_token, fingerprint, base_url="production")
```

Opsi lain (`timeout`, `maxRetries`, `userAgent`, transport) sama seperti
[Konfigurasi](./03-configuration.md), kecuali `signatureTtl` (tidak relevan).

---

## 8.2 `request()` — minta akses API

Membuat kredensial berstatus `pending`. Admin lalu meng-*approve* dan
menetapkan `permissions`.

```ts
const r = await creds.credentials.request();
// r.data: { api_key: null, secret_key: null, permissions: [], status: "pending", ... }
```

```php
$r = $creds->credentials->request();
```

```python
r = creds.credentials.request()
```

Error `400` (`BadRequestError`) jika sudah ada kredensial:

| Kondisi | Pesan |
|---------|-------|
| status `active` | "Anda sudah memiliki akses api" |
| status `pending` | "Permintaan sudah terkirim, silahkan tunggu konfirmasi dari admin" |
| status `revoked` | "Permintaan anda ditolak, silahkan hubungi admin" |

---

## 8.3 `get()` — lihat kredensial saat ini

```ts
const cur = await creds.credentials.get();
if (cur.data) {
  console.log(cur.data.api_key);
  console.log(cur.data.secret_key);   // di-decrypt server saat status "active"
  console.log(cur.data.permissions, cur.data.status, cur.data.approved_at);
}
```

`data` bernilai `null` jika user belum pernah request. Bentuk saat ada:

```json
{
  "api_key": "d4c997…",
  "secret_key": "plg_0512d9…",
  "permissions": ["message:send", "account:list"],
  "status": "active",
  "approved_at": "2026-04-01T08:00:00.000Z",
  "requested_at": "2026-03-30T10:00:00.000Z"
}
```

---

## 8.4 `regenerate()` — rotasi api_key + secret_key

Hanya berhasil bila status `active`. Mengembalikan `secret_key` **plaintext
sekali ini saja** — simpan segera. Kredensial lama langsung tidak berlaku.

```ts
const rg = await creds.credentials.regenerate();
saveSecret(rg.data.api_key, rg.data.secret_key);
```

```php
$rg = $creds->credentials->regenerate();
```

```python
rg = creds.credentials.regenerate()
```

Error:

| Kondisi | Error |
|---------|-------|
| Belum ada kredensial | `NotFoundError` (404) |
| Status belum `active` | `BadRequestError` (400) — "regenerate hanya tersedia setelah approval admin" |

---

## 8.5 `messages()` — riwayat pesan API (paginated)

```ts
const hist = await creds.credentials.messages({ page: 1, limit: 20 });
console.log(hist.data);       // array pesan
console.log(hist.metadata);   // { page, limit, total, total_pages }  (dari `meta`)
```

```php
$hist = $creds->credentials->messages(['page' => 1, 'limit' => 20]);
```

```python
hist = creds.credentials.messages(page=1, limit=20)
```

---

## 8.6 `subscribeWebhook()` — daftarkan endpoint webhook

```ts
await creds.credentials.subscribeWebhook({
  webhookUrl: "https://app.contoh.com/webhooks/plasgos",
  verifyToken: "rahasia-yang-anda-tentukan",
});
```

```php
$creds->credentials->subscribeWebhook([
    'webhook_url'  => 'https://app.contoh.com/webhooks/plasgos',
    'verify_token' => 'rahasia-yang-anda-tentukan',
]);
```

```python
creds.credentials.subscribe_webhook(
    webhook_url="https://app.contoh.com/webhooks/plasgos",
    verify_token="rahasia-yang-anda-tentukan",
)
```

### Yang terjadi di server sebelum URL disimpan

1. Server mengirim **`GET <webhookUrl>?verify_token=<token>`** dengan header
   `x-hub-token: <token>`.
2. Endpoint Anda **harus** membalas `200` dan mengembalikan token yang sama
   (di body sebagai `verify_token`, `token`, atau `challenge` — boleh nested di
   bawah `data`).
3. Baru setelah itu `webhook_url` + `verify_token` disimpan.

Proteksi SSRF: URL tidak boleh menunjuk ke host lokal/privat/loopback.

Jika verifikasi gagal → `BadRequestError` (400) dengan pesan penyebab.

Implementasi endpoint verifikasi + penerimaan event: [Webhooks](./09-webhooks.md).

---

## 8.7 Alur end-to-end

```text
1. creds.credentials.request()              → status "pending"
2. (admin approve + set permissions)         → status "active"
3. creds.credentials.get()                    → ambil api_key + secret_key
4. simpan ke secret manager
5. new PlasgosCrmClient(api_key, secret_key)  → mulai pakai /api/v1/*
6. creds.credentials.subscribeWebhook(...)    → daftar webhook (setelah endpoint siap)
7. (opsional) creds.credentials.regenerate()  → rotasi berkala
```

---

[← WhatsApp Official](./07-official-whatsapp.md) · [Webhooks →](./09-webhooks.md)
