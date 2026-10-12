# 2. Autentikasi & scope

## Kunci API

Tenant meminta kunci API di dashboard Plasgos CRM; admin Plasgos menyetujui dan menetapkan scope-nya.
Pasangannya:

- **API key** — dikirim apa adanya di header.
- **Secret key** (`plg_…`) — hanya untuk menandatangani; tak pernah dikirim.

SDK menandatangani setiap permintaan secara otomatis. Bagian berikut hanya perlu dibaca bila Anda
memanggil API tanpa SDK atau ingin memahami galat tanda tangan.

## Tanda tangan v3

```
string_to_sign = timestamp + "\n" + METHOD + "\n" + request_target + "\n" + hex(sha256(raw_body))
signature      = "v1=" + hex(hmac_sha256(secret_key, string_to_sign))
```

| Header | Isi |
|---|---|
| `Plasgos-Api-Key` | API key |
| `Plasgos-Timestamp` | Waktu Unix (detik) |
| `Plasgos-Signature` | `v1=<hex>` |

- `request_target` = path + query **persis seperti dikirim**, mis. `/api/v3/contacts?search=budi%20santoso&page=2`
  (query RFC 3986: spasi `%20`).
- `raw_body` = byte isi permintaan yang dikirim (kosong untuk GET → hash string kosong).
- Selisih jam lebih dari 5 menit ditolak `signature_expired` (`details.server_time` berisi jam server) —
  sinkronkan jam mesin Anda (NTP).

Fungsi penanda tangan juga diekspor untuk alat bantu atau pengujian:

| Bahasa | Fungsi |
|---|---|
| Node | `signV3`, `signatureHeadersV3`, `stringToSign` |
| PHP | `Plasgos\CrmSdk\Signature::sign()`, `::headers()`, `::stringToSign()` |
| Python | `sign_v3`, `signature_headers_v3`, `string_to_sign` |

Vektor uji bersama: [`../vectors-v3.json`](../vectors-v3.json).

## Scope

| Scope | Membuka |
|---|---|
| `account:read` | `GET /me` (selalu dimiliki kunci aktif) |
| `channels:read` | channel, titik kirim, template, variabel template |
| `messages:read` / `messages:write` | baca pesan & cek penerima / kirim pesan |
| `contacts:read` / `contacts:write` | kontak & grup / buat, upsert, ubah kontak |
| `leads:read` / `leads:write` | pipeline & lead / buat lead |
| `broadcasts:read` / `broadcasts:write` | broadcast / buat & batalkan |
| `followups:read` / `followups:write` | followup & pendaftaran / daftarkan & keluarkan |
| `webhooks:manage` | langganan webhook |

Izin v1 yang sudah dimiliki kunci diterjemahkan otomatis ke padanan v3-nya (mis. `message:send` →
`messages:write` + `messages:read`). Scope **tanpa padanan v1** (kontak, lead, followup, webhook) harus
diberikan admin per kunci — kunci yang sudah aktif tidak mendapatkannya otomatis. Scope yang kurang
dijawab `403 scope_missing`; fitur di luar paket tenant dijawab `403 not_entitled`.

Cek scope kunci Anda: `me.get()` → `data.scopes`.
