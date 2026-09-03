# 5. Messages — WhatsApp Unofficial

[← Response Envelope & Error](./04-responses-and-errors.md) · [Accounts & References →](./06-accounts-and-references.md)

---

Namespace `messages` mengirim pesan lewat akun WhatsApp **unofficial**
(nomor yang terhubung via QR/pairing di CRM). Untuk WhatsApp Business API
resmi, lihat [halaman 7](./07-official-whatsapp.md).

| Method | Endpoint | Permission |
|--------|----------|-----------|
| `send` | `POST /api/v1/messages` | `message:send` |
| `list` | `GET /api/v1/messages` | `message:list` |
| `get` | `GET /api/v1/messages/{message_id}` | `message:detail` |

---

## 5.1 `send` — kirim pesan

### Field request

| SDK (Node/PHP camel) | Wire | Wajib | Aturan |
|----------------------|------|-------|--------|
| `messageId` | `message_id` | tidak* | Unik **global**. Duplikat → `409` (`ConflictError`). *Bila kosong, SDK generate UUIDv4 dan mengembalikannya. |
| `channel` | `channel` | ya | `whatsapp` \| `telegram` \| `messanger` \| `instagram` (`messanger` memang salah eja di server) |
| `account.accountId` | `account.account_id` | ya | Mongo ObjectId akun yang berstatus `connected` |
| `receiver.phoneNumber` | `receiver.phone_number` | ya | Regex `^(?:\+62\|62\|08)[0-9]{7,12}$`, maks 15 karakter |
| `receiver.name` | `receiver.name` | ya | Minimal 1 karakter |
| `content.type` | `content.type` | ya | `text` \| `image` \| `video` \| `document` |
| `content.data.*` | `content.data.*` | tergantung type | lihat tabel di §5.2 |

> **Python & PHP:** isi `content.data` memakai **snake_case** apa adanya
> (`mime_type`, `file_name`). **Node:** memakai camelCase (`mimeType`,
> `fileName`) dan SDK yang memetakan.

### Nilai kembalian

`Envelope` berisi resource pesan pada `data`. `message_id` yang benar-benar
dikirim tersedia di:

- Node: `result.messageId`
- PHP: `$result->metadata['message_id']`
- Python: `result.metadata["message_id"]`

Bentuk `data`:

```json
{
  "message_id": "…",
  "channel": "whatsapp",
  "account": { "id": "…", "name": "…", "phone_number": "…" },
  "receiver": { "phone_number": "62812…", "name": "Budi", "jid": "62812…@s.whatsapp.net" },
  "content": { "type": "text", "data": { "text": "…" } },
  "sent": true,
  "sent_at": 1775644335000
}
```

---

## 5.2 Tipe konten

### Text

```jsonc
{ "type": "text", "data": { "text": "Isi pesan" } }
```

### Image / Video

```jsonc
{ "type": "image", "data": { "url": "https://cdn.contoh.com/foto.jpg", "caption": "opsional" } }
{ "type": "video", "data": { "url": "https://cdn.contoh.com/klip.mp4", "caption": "opsional" } }
```

### Document

```jsonc
{
  "type": "document",
  "data": {
    "url": "https://cdn.contoh.com/invoice.pdf",
    "caption": "opsional",
    "mime_type": "application/pdf",   // Node: mimeType
    "file_name": "invoice.pdf"        // Node: fileName
  }
}
```

| type | Field `data` yang dipakai |
|------|---------------------------|
| `text` | `text` (wajib) |
| `image` | `url` (wajib), `caption` |
| `video` | `url` (wajib), `caption` |
| `document` | `url` (wajib), `caption`, `mime_type`, `file_name` |

URL media/dokumen harus dapat diakses publik oleh server Plasgos.

---

## 5.3 Contoh — Node.js / TypeScript

```ts
// Teks
const r1 = await client.messages.send({
  channel: "whatsapp",
  account: { accountId: ACCOUNT_ID },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: { type: "text", data: { text: "Pesanan #INV-001 diproses." } },
});
console.log(r1.messageId);

// Gambar dengan message_id sendiri (untuk korelasi)
await client.messages.send({
  messageId: "order-INV-001-shipped",
  channel: "whatsapp",
  account: { accountId: ACCOUNT_ID },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: {
    type: "image",
    data: { url: "https://cdn.contoh.com/resi.jpg", caption: "Resi pengiriman" },
  },
});

// Dokumen
await client.messages.send({
  channel: "whatsapp",
  account: { accountId: ACCOUNT_ID },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: {
    type: "document",
    data: {
      url: "https://cdn.contoh.com/invoice-INV-001.pdf",
      mimeType: "application/pdf",
      fileName: "invoice-INV-001.pdf",
      caption: "Invoice terlampir",
    },
  },
});

// List & detail
const all = await client.messages.list();
const one = await client.messages.get("order-INV-001-shipped");
```

---

## 5.4 Contoh — PHP

```php
use Plasgos\CrmSdk\Enum\Channel;

// Teks
$r1 = $client->messages->send([
    'channel'    => Channel::WhatsApp,      // atau 'whatsapp'
    'account_id' => $accountId,
    'receiver'   => ['phone_number' => '6281234567890', 'name' => 'Budi'],
    'content'    => ['type' => 'text', 'data' => ['text' => 'Pesanan #INV-001 diproses.']],
]);
echo $r1->metadata['message_id'];

// Dokumen (data pakai snake_case)
$client->messages->send([
    'channel'    => 'whatsapp',
    'account_id' => $accountId,
    'receiver'   => ['phone_number' => '6281234567890', 'name' => 'Budi'],
    'content'    => [
        'type' => 'document',
        'data' => [
            'url'       => 'https://cdn.contoh.com/invoice-INV-001.pdf',
            'mime_type' => 'application/pdf',
            'file_name' => 'invoice-INV-001.pdf',
            'caption'   => 'Invoice terlampir',
        ],
    ],
]);

$all = $client->messages->list();
$one = $client->messages->get('order-INV-001-shipped');
```

---

## 5.5 Contoh — Python

```python
# Teks
r1 = client.messages.send(
    channel="whatsapp",
    account_id=ACCOUNT_ID,
    receiver={"phone_number": "6281234567890", "name": "Budi"},
    content={"type": "text", "data": {"text": "Pesanan #INV-001 diproses."}},
)
print(r1.metadata["message_id"])

# Video
client.messages.send(
    message_id="promo-2026-02",
    channel="whatsapp",
    account_id=ACCOUNT_ID,
    receiver={"phone_number": "6281234567890", "name": "Budi"},
    content={
        "type": "video",
        "data": {"url": "https://cdn.contoh.com/promo.mp4", "caption": "Promo Februari"},
    },
)

# Dokumen (data pakai snake_case)
client.messages.send(
    channel="whatsapp",
    account_id=ACCOUNT_ID,
    receiver={"phone_number": "6281234567890", "name": "Budi"},
    content={
        "type": "document",
        "data": {
            "url": "https://cdn.contoh.com/invoice-INV-001.pdf",
            "mime_type": "application/pdf",
            "file_name": "invoice-INV-001.pdf",
        },
    },
)

all_msgs = client.messages.list()
one = client.messages.get("promo-2026-02")
```

---

## 5.6 Error khas endpoint ini

| Situasi | Error |
|---------|-------|
| `message_id` sudah pernah dipakai | `ConflictError` (409) |
| Nomor tidak sesuai regex | `ValidationError` (422), `path: ["receiver","phone_number"]` |
| `account_id` bukan ObjectId / akun tidak ada | `ValidationError` / `NotFoundError` |
| Akun tidak berstatus `connected` | `ValidationError` (400) atau `NotFoundError` — pesan menyebut "not connected" |
| `type` tidak dikenal | `ValidationError` (422) |
| Kuota hit harian habis | `RateLimitError` (429) |
| Scope `message:send` tidak dimiliki | `PermissionError` (403) |

Selalu sediakan `message_id` yang stabil per kejadian bisnis jika Anda ingin
retry aman — lihat [Recipes §10.1](./10-recipes.md#101-retry-idempoten).

---

[← Response Envelope & Error](./04-responses-and-errors.md) · [Accounts & References →](./06-accounts-and-references.md)
