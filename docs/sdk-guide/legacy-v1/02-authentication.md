# 2. Autentikasi

[← Getting Started](./01-getting-started.md) · [Konfigurasi Client →](./03-configuration.md)

---

Ada **dua mekanisme** yang berbeda. Jangan dicampur.

| | Signed API | Credential Management |
|-|-----------|---------------------|
| Prefix | `/api/v1/*` | `/v2/integration/*` |
| Header | `x-api-key`, `x-timestamp`, `x-signature` | `Authorization: Bearer <token>`, `fingerprint` |
| Client SDK | `PlasgosCrmClient` / `Client` | `PlasgosCrmCredentialsClient` / `CredentialsClient` |
| Kredensial | `api_key` + `secret_key` | Access token sesi user CRM + fingerprint |

---

## 2.1 Signed API — cara kerja signature

Untuk setiap request `/api/v1/*`, SDK mengirim tiga header:

| Header | Nilai |
|--------|-------|
| `x-api-key` | `api_key` (64 karakter hex) |
| `x-timestamp` | Waktu Unix **dalam detik** saat request dibuat |
| `x-signature` | `lowercase_hex( HMAC_SHA256( secret_key, "{api_key}:{timestamp}" ) )` |

Poin penting:

- String yang di-sign **hanya** `"{api_key}:{timestamp}"` — **bukan** method,
  path, query, atau body.
- `secret_key` adalah nilai plaintext (`plg_` + hex) yang diberikan saat approve
  / regenerate. Bukan bentuk apa pun yang tersimpan/terenkripsi.
- Timestamp harus berada dalam **300 detik** dari waktu server
  (`SIGNATURE_TTL`). Di luar itu → **HTTP 408** (`SignatureExpiredError`).
- SDK menghitung signature **baru untuk setiap request** — tidak pernah di-cache.

### Rumus signature (referensi manual)

```
data      = api_key + ":" + timestamp          # timestamp = detik Unix
signature = HMAC_SHA256(secret_key, data)       # hex, lowercase
```

Contoh vektor (untuk uji implementasi):

| api_key | secret_key | timestamp | signature |
|---------|-----------|-----------|-----------|
| `test_key` | `test_secret` | `1700000000` | `e18a0c152134399f8efa60f058ced975a2f24657e231c3bd95351d527a3c1fcb` |

Vektor lengkap: [`../../vectors.json`](../../vectors.json).

### Membuat client

```ts
// Node
import { PlasgosCrmClient } from "plasgos-crm-sdk";
const client = new PlasgosCrmClient(apiKey, secretKey, { baseUrl: "production" });
```

```php
// PHP
use Plasgos\CrmSdk\{Client, ClientConfig};
use Plasgos\CrmSdk\Enum\BaseUrl;
$client = new Client($apiKey, $secretKey, new ClientConfig(baseUrl: BaseUrl::Production));
```

```python
# Python
from plasgos_crm import PlasgosCrmClient
client = PlasgosCrmClient(api_key, secret_key, base_url="production")
```

### Melihat header yang akan dikirim (debugging)

```ts
client.signatureHeaders();            // { "x-api-key", "x-timestamp", "x-signature" }
client.signatureHeaders(1700000000);  // dengan timestamp tetap
```

```php
$client->signatureHeaders();          // array
$client->signatureHeaders(1700000000);
```

```python
client.signature_headers()
client.signature_headers(1700000000)
```

Atau langsung dari helper tanpa membuat client:

```ts
import { computeSignature, signRequest } from "plasgos-crm-sdk";
computeSignature(apiKey, secretKey, 1700000000);
```

```php
\Plasgos\CrmSdk\Signature::compute($apiKey, $secretKey, 1700000000);
\Plasgos\CrmSdk\Signature::headers($apiKey, $secretKey);
```

```python
from plasgos_crm import compute_signature, signature_headers
compute_signature(api_key, secret_key, 1700000000)
```

---

## 2.2 Clock skew (penyebab 408 paling umum)

Karena signature terikat timestamp dengan toleransi 300 detik, **jam server
aplikasi Anda harus akurat**.

- Pakai NTP di server produksi.
- Kalau CI/kontainer sering drift, sinkronkan waktu sebelum menjalankan job.
- `signatureTtl` di opsi client **hanya informasi** (untuk diagnosa lokal);
  yang menegakkan tetap server.

Kalau dapat `SignatureExpiredError` padahal jam benar → cek apakah ada proxy /
retry yang menahan request lebih dari 5 menit.

---

## 2.3 Permission scope

API key memiliki daftar `permissions`. Endpoint yang tidak tercakup →
`PermissionError` (403). Katalog scope:

| Grup | Scope |
|------|-------|
| Unofficial | `message:send`, `message:list`, `message:detail`, `account:list`, `account:detail` |
| Official | `official:message:send`, `official:account:list`, `official:phone-numbers:list`, `official:templates:list`, `official:broadcast:send`, `official:broadcast:list`, `official:broadcast:monitoring` |

`references.variables()` tidak mengecek scope apa pun (cukup signature valid).
`references.contacts()` hanya butuh signature valid.

> ⚠️ **Quirk server:** jika API key **belum di-approve**, middleware permission
> bisa melempar **HTTP 500** dengan pesan `"You don't have permission"`
> alih-alih 403. SDK memperlakukannya sebagai `ServerError` — periksa pesannya.

---

## 2.4 Credential Management — Bearer + fingerprint

Endpoint `/v2/integration/*` memakai **sesi user CRM**, bukan signature:

| Header | Nilai |
|--------|-------|
| `Authorization` | `Bearer <access token terenkripsi>` |
| `fingerprint` | Fingerprint device yang terikat sesi tersebut |

Token ini adalah string yang **persis** dikeluarkan sesi login CRM Anda. SDK
**tidak** mengenkripsi, refresh, atau memvalidasinya — hanya meneruskannya.

```ts
// Node
import { PlasgosCrmCredentialsClient } from "plasgos-crm-sdk";
const creds = new PlasgosCrmCredentialsClient(accessToken, fingerprint, { baseUrl: "production" });
await creds.credentials.get();
```

```php
// PHP
use Plasgos\CrmSdk\CredentialsClient;
$creds = new CredentialsClient($accessToken, $fingerprint);
$creds->credentials->get();
```

```python
# Python
from plasgos_crm import PlasgosCrmCredentialsClient
creds = PlasgosCrmCredentialsClient(access_token, fingerprint, base_url="production")
creds.credentials.get()
```

Jika token/fingerprint salah atau sesi kadaluarsa → `AuthenticationError` (401).

Selengkapnya: [Credential Management](./08-credential-management.md).

---

## 2.5 Cara memperoleh kredensial

1. **Request akses** (butuh login CRM):
   `creds.credentials.request()` → membuat kredensial berstatus `pending`.
2. **Admin approve** dari panel admin, menetapkan `permissions`. Setelah itu
   status jadi `active` dan `api_key` + `secret_key` terisi.
3. **Ambil kredensial**: `creds.credentials.get()` mengembalikan `api_key` dan
   `secret_key` (di-decrypt server) selama status `active`.
4. Simpan `secret_key` dengan aman. Kalau hilang → `creds.credentials.regenerate()`
   (mengganti `api_key` + `secret_key`, hanya bila status `active`).

Setelah punya `api_key` + `secret_key`, gunakan `PlasgosCrmClient` untuk semua
operasi `/api/v1/*`.

---

## 2.6 Keamanan penyimpanan kredensial

- Simpan di environment variable / secret manager, **jangan** di source code
  atau repo.
- `secret_key` setara password — hanya tampil sekali saat request/regenerate.
- Batasi `permissions` seminimal yang dibutuhkan integrasi.
- Rotasi berkala via `regenerate()`; kredensial lama langsung tidak berlaku.
- `verify_token` webhook juga rahasia — perlakukan sama.

---

[← Getting Started](./01-getting-started.md) · [Konfigurasi Client →](./03-configuration.md)
