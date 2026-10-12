# 4. Response Envelope & Error

[← Konfigurasi Client](./03-configuration.md) · [Messages →](./05-messages.md)

---

## 4.1 Kenapa perlu normalisasi

Server `api-crm` mengembalikan **beberapa bentuk envelope** yang tidak
konsisten:

| Sumber | Bentuk |
|--------|--------|
| Mayoritas `/api/v1/*` & semua `/v2/integration/*` | `{ status, message, data, metadata }` |
| `GET /api/v1/references/variables` | `{ success: true, data: [...] }` |
| Gagal signature / auth | `{ status, message, metadata: { timestamp } }` |
| Gagal entitlement (403 tanpa plan, 429 kuota) | `{ success: false, message }` |
| Gagal validasi | `{ status, message, errors: [ { message, path } ] }` |
| `/v2/integration/messages` | `{ status, message, data, meta }` |

SDK menyeragamkan semuanya menjadi satu `Envelope`.

---

## 4.2 Bentuk `Envelope`

```ts
// Node
interface Envelope<T = unknown> {
  status: number | boolean;   // status yang dilaporkan body, else HTTP status
  message: string | null;
  data: T;
  metadata: Record<string, unknown>;  // `metadata` ATAU `meta` ATAU {}
  raw: unknown;               // body mentah hasil parse
  httpStatus: number;         // HTTP status sebenarnya
}
```

```php
// PHP — Plasgos\CrmSdk\Envelope (readonly)
$e->status;      // int|bool
$e->message;     // ?string
$e->data;        // mixed
$e->metadata;    // array
$e->raw;         // mixed
$e->httpStatus;  // int
$e->toArray();   // array ringkas
```

```python
# Python — dataclass frozen
e.status        # int | bool
e.message       # str | None
e.data          # Any
e.metadata      # dict
e.raw           # Any
e.http_status   # int
```

### Aturan normalisasi

| Body server | Hasil |
|-------------|-------|
| `{ success: true, data }` | `status = httpStatus`, `message = null`, `data = body.data`, `metadata = {}` |
| `{ status, message, data, metadata }` | disalin apa adanya |
| `{ status, message, data, meta }` | `metadata = body.meta` |
| Bukan objek (kosong / teks) | `data = body`, `metadata = {}` |

`raw` selalu berisi body asli — pakai bila butuh field yang tidak dipetakan.

---

## 4.3 Kapan error dilempar

Sebuah panggilan melempar error bertipe jika:

- HTTP status **non-2xx**, **atau**
- body memiliki `success: false`, **atau**
- body memiliki `status` numerik **≥ 400** (walau HTTP-nya 200 — ini terjadi
  pada beberapa endpoint lama).

---

## 4.4 Hierarki error

```
PlasgosApiError                     (base — semua error SDK)
├── AuthenticationError             401
├── PermissionError                 403 (scope kurang / konteks tak diizinkan)
│   └── PlanError                   403 (paket langganan tak menyertakan API)
├── NotFoundError                   404
├── SignatureExpiredError           408 (timestamp di luar window)
├── ConflictError                   409 (message_id duplikat)
├── ValidationError                 422 / 400 dengan `errors[]`
├── BadRequestError                 400 tanpa `errors[]`
├── RateLimitError                  429 (kuota hit harian habis)
├── ServerError                     >= 500
└── TransportError                  jaringan / timeout / DNS (tak ada respons)

WebhookVerificationError            (bukan turunan PlasgosApiError — dari verifyWebhook)
```

### Field pada setiap error

| Field | Node | PHP | Python |
|-------|------|-----|--------|
| Pesan | `.message` | `->getMessage()` | `.message` / `str(e)` |
| HTTP status | `.httpStatus` | `->httpStatus` | `.http_status` |
| Status dari body | `.apiStatus` | `->apiStatus` | `.api_status` |
| Isu validasi | `.errors` | `->errors` | `.errors` |
| Body mentah | `.raw` | `->raw` | `.raw` |
| Request id | `.requestId` | `->requestId` | `.request_id` |

`errors` adalah array `{ message, path }` (path = array segmen field).

---

## 4.5 Tabel mapping status → error

| Kondisi | Error |
|---------|-------|
| HTTP 401 | `AuthenticationError` |
| HTTP 403 + pesan menyebut plan / "tidak menyertakan" | `PlanError` |
| HTTP 403 lainnya | `PermissionError` |
| HTTP 404 | `NotFoundError` |
| HTTP 408 | `SignatureExpiredError` |
| HTTP 409 | `ConflictError` |
| HTTP 422, atau 400 dengan `errors[]` | `ValidationError` |
| HTTP 429 | `RateLimitError` |
| HTTP 400 tanpa `errors[]` | `BadRequestError` |
| HTTP ≥ 500 | `ServerError` |
| jaringan / timeout | `TransportError` |
| non-2xx lain | `PlasgosApiError` (base) |

> ⚠️ **Quirk:** API key yang belum di-approve bisa memicu **HTTP 500**
> (`ServerError`) dengan pesan memuat `"You don't have permission"` — bukan 403.
> Cek `error.message` bila mendapat `ServerError` tak terduga saat auth.

---

## 4.6 Pola penanganan error

### Node.js / TypeScript

```ts
import {
  ValidationError, RateLimitError, SignatureExpiredError,
  PlanError, PlasgosApiError,
} from "plasgos-crm-sdk";

try {
  await client.messages.send(/* ... */);
} catch (err) {
  if (err instanceof ValidationError) {
    for (const issue of err.errors) console.error(issue.path, issue.message);
  } else if (err instanceof RateLimitError) {
    // tunggu / antre ulang
  } else if (err instanceof SignatureExpiredError) {
    // periksa jam server aplikasi
  } else if (err instanceof PlanError) {
    // upgrade paket
  } else if (err instanceof PlasgosApiError) {
    console.error(err.httpStatus, err.message, err.raw);
  } else {
    throw err; // bukan error SDK
  }
}
```

### PHP

```php
use Plasgos\CrmSdk\Exception\{ValidationException, RateLimitException, PlasgosApiException};

try {
    $client->messages->send([/* ... */]);
} catch (ValidationException $e) {
    foreach ($e->errors as $issue) {
        error_log(implode('.', $issue['path'] ?? []) . ': ' . $issue['message']);
    }
} catch (RateLimitException $e) {
    // back off
} catch (PlasgosApiException $e) {
    error_log($e->httpStatus . ' ' . $e->getMessage());
}
```

### Python

```python
from plasgos_crm import (
    ValidationError, RateLimitError, SignatureExpiredError, PlasgosApiError,
)

try:
    client.messages.send(...)
except ValidationError as e:
    for issue in e.errors:
        print(issue.get("path"), issue["message"])
except RateLimitError:
    ...  # back off
except SignatureExpiredError:
    ...  # cek jam
except PlasgosApiError as e:
    print(e.http_status, e.message, e.raw)
```

---

## 4.7 Kode status yang mungkin muncul

| Kode | Arti |
|------|------|
| 200 | Sukses |
| 201 | Broadcast berhasil dibuat |
| 400 | Bad request |
| 401 | Header autentikasi kurang / tidak valid |
| 403 | API key / signature / permission tidak valid, atau paket tanpa akses API |
| 404 | Resource tidak ditemukan |
| 408 | `x-timestamp` kadaluarsa (di luar 300 detik) |
| 409 | `message_id` duplikat |
| 422 | Validasi body gagal |
| 429 | Kuota hit API harian terlampaui |
| 500 | Error server (termasuk quirk key belum di-approve) |

Tidak ada header sisa kuota pada respons 429 — hanya pesan
`"Batas hit API harian tercapai (X/Y)"`.

---

[← Konfigurasi Client](./03-configuration.md) · [Messages →](./05-messages.md)
