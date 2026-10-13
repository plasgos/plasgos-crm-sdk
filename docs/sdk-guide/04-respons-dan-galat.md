# 4. Respons & galat

## Amplop API

```json
{ "success": true, "message": "Kontak dibuat", "data": { "id": "66f1…" }, "meta": { "request_id": "req_…", "created": true } }
{ "success": false, "message": "Isi permintaan tidak sesuai format", "error": { "code": "validation_failed", "type": "invalid_request", "details": null, "fields": [ { "field": "contact.phone_number", "code": "required", "message": "Wajib diisi" } ], "doc_url": "…" }, "meta": { "request_id": "req_…" } }
```

## `ApiResponse`

| Node / PHP | Python | Isi |
|---|---|---|
| `httpStatus` | `http_status` | 200, 201, 202 … |
| `message` | `message` | teks untuk orang (Bahasa Indonesia) |
| `data` | `data` | isi (format kabel) |
| `meta` | `meta` | mis. `created` (resource baru dibuat?), `contact_created`, `rejected_recipients` |
| `requestId` | `request_id` | sebutkan saat menghubungi Plasgos |
| `rateLimit` | `rate_limit` | `limit`, `remaining`, `reset` (detik) |
| `replayed` | `replayed` | `true` bila server mengulang jawaban pertama untuk Idempotency-Key ini |
| `header(name)` | `header(name)` | header respons apa pun |

## Tipe

Tipe permintaan & respons **dibangkitkan dari OpenAPI** (`scripts/generate-types.mjs`, diperiksa CI),
jadi selalu sama dengan API:

- **Node:** `import { type Types } from "plasgos-crm-sdk"` → `Types.Contact`, `Types.Message`,
  `Types.Operations["sendMessage"]["body"]`. Setiap metode resource sudah bertipe (`data`, `meta`, item
  `Page`).
- **Python:** `from plasgos_crm.v3 import types as t` → `t.Contact`, `t.SendMessageBody`,
  `t.ListContactsQuery` (TypedDict); `ApiResponse[t.Contact]`, `Page[t.Contact]`.
- **PHP:** larik format kabel, dengan array shape PHPStan/Psalm per skema dan per operasi
  (`Plasgos\CrmSdk\Types\ApiTypes`). Setiap metode resource bertipe — `Response<GetContactData,
  GetContactMeta>`, `Page<ListContactsItem>`, body `SendMessageBody` — sehingga PHPStan/IDE tahu
  `$r->data['tags']` adalah `list<string>`. Impor skema di kelas Anda:
  `@phpstan-import-type Contact from \Plasgos\CrmSdk\Types\ApiTypes`.

## `Page` (metode daftar)

- `items` — isi halaman ini; `pagination` — `page`, `per_page`, `total`, `total_pages`, `has_next_page`, `has_prev_page`.
- `hasNextPage()` / `nextPage()` (`has_next_page()` / `next_page()` di Python).
- `autoPaging()` (`auto_paging()`) — semua item dari semua halaman, halaman berikutnya diambil saat dibutuhkan.

```ts
for await (const contact of (await client.contacts.list({ per_page: 100 })).autoPaging()) { /* … */ }   // Node
```

```php
foreach ($client->contacts->list(['per_page' => 100])->autoPaging() as $contact) { /* … */ }            // PHP
```

```python
for contact in client.contacts.list(per_page=100).auto_paging(): ...                                    # Python
```

## Hierarki galat

| HTTP | Node / Python | PHP |
|---|---|---|
| 401 `signature_expired` | `SignatureExpiredError` (turunan `AuthenticationError`) | `SignatureExpiredException` |
| 401 | `AuthenticationError` | `AuthenticationException` |
| 403 `not_entitled` | `PlanError` (turunan `PermissionError`) | `PlanException` |
| 403 | `PermissionError` | `PermissionException` |
| 404 | `NotFoundError` | `NotFoundException` |
| 409 | `ConflictError` | `ConflictException` |
| 400 tanpa `fields` | `BadRequestError` | `BadRequestException` |
| 400 dengan `fields`, 413, 422 | `ValidationError` | `ValidationException` |
| 429 | `RateLimitError` | `RateLimitException` |
| 502 | `ChannelError` (turunan `ServerError`) | `ChannelException` |
| 503 | `ServiceUnavailableError` (turunan `ServerError`) | `ServiceUnavailableException` |
| 5xx lain | `ServerError` | `ServerException` |
| tanpa respons | `TransportError` (`transport_error`) | `TransportException` |

Semuanya turunan `PlasgosApiError` / `PlasgosApiException` dan membawa: `errorCode` (`error_code`),
`errorType`, `details`, `fields` (`[{field, code, message}]`), `docUrl`, `requestId`, `retryAfter`.

```ts
try { await client.contacts.create({ phone_number: "0812…" }); }
catch (e) { if (e instanceof ConflictError && e.errorCode === "identity_taken") console.log(e.details.contact_id); else throw e; }
```

```php
try { $client->contacts->create(['phone_number' => '0812…']); }
catch (ConflictException $e) { if ($e->errorCode === 'identity_taken') { echo $e->details['contact_id']; } }
```

```python
try:
    client.contacts.create({"phone_number": "0812…"})
except ConflictError as e:
    if e.error_code == "identity_taken":
        print(e.details["contact_id"])
```

## Kode galat umum

| Kode | Arti |
|---|---|
| `api_key_invalid`, `api_key_not_active`, `api_key_revoked` | kunci tak dikenal / belum disetujui / dicabut |
| `signature_missing`, `signature_invalid`, `signature_expired` | header tanda tangan tak ada / salah / jam berselisih > 5 menit |
| `scope_missing`, `not_entitled` | scope kunci kurang / fitur di luar paket tenant |
| `rate_limited`, `daily_quota_exceeded` | batas per menit (pulih sendiri) / kuota harian habis |
| `idempotency_in_progress` | permintaan dengan kunci yang sama masih diproses (409) |
| `idempotency_key_reused`, `idempotency_key_invalid` | kunci sama untuk isi berbeda / format kunci salah (8–255 karakter ASCII) |
| `validation_failed`, `malformed_json`, `payload_too_large` | isi tak sesuai (lihat `fields`; objek `contact` salah bentuk → kode field `invalid_contact`) / JSON rusak / terlalu besar |
| `*_not_found` | `endpoint_`, `message_`, `contact_`, `group_`, `pipeline_`, `stage_`, `lead_`, `broadcast_`, `followup_`, `enrollment_`, `webhook_`, `template_` |
| `requires_template` | WhatsApp resmi di luar jendela 24 jam — kirim template |
| `identity_taken` | nomor/email sudah milik kontak lain (`details.contact_id`) |

Galat khusus tiap endpoint ada di halaman masing-masing. Katalog lengkap (status & arti setiap kode) ada di OpenAPI: `components.x-error-codes`.

## Coba ulang otomatis

| Galat | Diulang? |
|---|---|
| `TransportError`, 502, 503 | Ya, semua metode |
| 429 `rate_limited` | Ya; menunggu `Retry-After` bila ≤ `maxRetryWait`, lebih lama dari itu dilempar |
| 409 `idempotency_in_progress` | Ya, dengan kunci yang sama — permintaan pertama masih diproses (mis. habis waktu di sisi klien); begitu selesai, server mengulang jawabannya |
| 500 biasa | Hanya GET (tulisan mungkin sudah sebagian jalan) |
| `daily_quota_exceeded`, 4xx lain (termasuk 409 `identity_taken`) | Tidak pernah |

Tanpa `Retry-After`, jeda = acak 0 … min(8 dtk, 0,5 dtk × 2^percobaan). Coba ulang memakai
Idempotency-Key yang sama, jadi aman untuk POST. Jawaban 5xx tak disimpan server, sehingga mengulang
dengan kunci yang sama memproses ulang permintaannya.
