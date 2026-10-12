# 7. WhatsApp Official (WABA)

[← Accounts & References](./06-accounts-and-references.md) · [Credential Management →](./08-credential-management.md)

---

Namespace `official.*` untuk WhatsApp Business API resmi (Meta). Alur umum:

1. `official.accounts.list()` → dapatkan akun WABA + phone number.
2. `official.accounts.phoneNumbers(accountId)` → pilih `phone_number_id`.
3. `official.templates.list(accountId, { category })` → pilih template `APPROVED`.
4. `official.messages.sendTemplate(...)` atau `official.broadcasts.create(...)`.

| Namespace.method | Endpoint | Permission |
|------------------|----------|-----------|
| `official.accounts.list` | `GET /api/v1/official/accounts` | `official:account:list` |
| `official.accounts.phoneNumbers` | `GET /api/v1/official/accounts/phone-numbers/{accountId}` | `official:phone-numbers:list` |
| `official.templates.list` | `GET /api/v1/official/templates/{accountId}` | `official:templates:list` |
| `official.messages.sendTemplate` | `POST /api/v1/official/messages` | `official:message:send` |
| `official.broadcasts.create` | `POST /api/v1/official/broadcasts` | `official:broadcast:send` |
| `official.broadcasts.list` | `GET /api/v1/official/broadcasts` | `official:broadcast:list` |
| `official.broadcasts.monitoring` | `GET /api/v1/official/broadcasts/{id}/monitoring` | `official:broadcast:monitoring` |
| `official.sharedWaba.sendOtp` | `POST /api/v1/official/shared-waba/otp/{serialToken}` | `official:message:send` |

> Python: `official.shared_waba.send_otp(...)`.

---

## 7.1 Akun & phone number

```ts
// Node
const accounts = await client.official.accounts.list();
const acc = accounts.data[0];                       // { id, name, phone_numbers: [...] , ... }

const phones = await client.official.accounts.phoneNumbers(acc.id);
const phoneNumberId = phones.data[0].phone_number_id;
```

```php
// PHP
$accounts = $client->official->accounts->list();
$phones = $client->official->accounts->phoneNumbers($accounts->data[0]['id']);
```

```python
# Python
accounts = client.official.accounts.list()
phones = client.official.accounts.phone_numbers(accounts.data[0]["id"])
```

`official.accounts.list()` juga menyertakan phone number yang aktif & approved.

---

## 7.2 Daftar template

```ts
const tpl = await client.official.templates.list(accountId, { category: "UTILITY" });
```

```php
$tpl = $client->official->templates->list($accountId, ['category' => 'UTILITY']);
```

```python
tpl = client.official.templates.list(account_id, category="UTILITY")
```

`category` opsional: `MARKETING` \| `UTILITY` \| `AUTHENTICATION` \| `OTP`.
Hanya template berstatus `APPROVED` yang dikembalikan.

---

## 7.3 Kirim template message

### Field request

| SDK (Node camel) | Wire | Keterangan |
|------------------|------|-----------|
| `phoneNumberId` | `phone_number_id` | dari §7.1 |
| `to` | `to` | nomor tujuan |
| `template.id` | `template.id` | ID template (min 10 karakter) |
| `template.name` | `template.name` | `^[a-z0-9_]+$`, min 3 |
| `template.category` | `template.category` | `TRANSACTIONAL` \| `MARKETING` \| `OTP` \| `UTILITY` \| `AUTHENTICATION` |
| `template.parameters.variables[]` | `…variables[]` | `{ type: "dynamic"\|"custom", name, value }` sesuai placeholder di Meta |
| `template.parameters.attachment` | `…attachment` | untuk header IMAGE/VIDEO/DOCUMENT |

Untuk `attachment` (Node → wire):

| Node | Wire |
|------|------|
| `type` | `type` (`image`\|`video`\|`document`) |
| `link` | `link` |
| `mimeType` | `mime_type` |
| `fileName` | `filename` |
| `originalName` | `original_name` |
| `fileSize` | `filesize` |

> PHP & Python: kirim `template` dengan **snake_case apa adanya** (mis.
> `mime_type`, `filename`, `original_name`, `filesize`).

Untuk kategori **OTP / AUTHENTICATION**, server dapat membangkitkan `otp_code`
otomatis bila tidak disertakan pada parameter.

### Contoh — Node

```ts
await client.official.messages.sendTemplate({
  phoneNumberId: "123456789012345",
  to: "6281234567890",
  template: {
    id: "1234567890123456",
    name: "order_update_v1",
    category: "UTILITY",
    parameters: {
      variables: [
        { type: "custom", name: "1", value: "Budi" },
        { type: "custom", name: "2", value: "INV-20260406-001" },
      ],
    },
  },
});

// Template dengan header dokumen unik
await client.official.messages.sendTemplate({
  phoneNumberId: "123456789012345",
  to: "6281234567890",
  template: {
    id: "1234567890123456",
    name: "invoice_v2",
    category: "UTILITY",
    parameters: {
      variables: [{ type: "custom", name: "1", value: "Budi" }],
      attachment: {
        type: "document",
        link: "https://cdn.contoh.com/INV-001.pdf",
        mimeType: "application/pdf",
        fileName: "INV-001.pdf",
        originalName: "INV-001.pdf",
        fileSize: 20480,
      },
    },
  },
});
```

### Contoh — PHP

```php
$client->official->messages->sendTemplate([
    'phone_number_id' => '123456789012345',
    'to'              => '6281234567890',
    'template' => [
        'id'       => '1234567890123456',
        'name'     => 'order_update_v1',
        'category' => 'UTILITY',
        'parameters' => [
            'variables' => [
                ['type' => 'custom', 'name' => '1', 'value' => 'Budi'],
                ['type' => 'custom', 'name' => '2', 'value' => 'INV-20260406-001'],
            ],
        ],
    ],
]);
```

### Contoh — Python

```python
client.official.messages.send_template(
    phone_number_id="123456789012345",
    to="6281234567890",
    template={
        "id": "1234567890123456",
        "name": "order_update_v1",
        "category": "UTILITY",
        "parameters": {
            "variables": [
                {"type": "custom", "name": "1", "value": "Budi"},
                {"type": "custom", "name": "2", "value": "INV-20260406-001"},
            ]
        },
    },
)
```

---

## 7.4 Broadcast

### `create` — buat broadcast

Mengembalikan **HTTP 201**. Maksimal **1000 penerima per hari** dikelompokkan
berdasarkan tanggal `scheduled_at`. `request_id` adalah kunci idempotensi.

| SDK (Node camel) | Wire | Keterangan |
|------------------|------|-----------|
| `accountId` | `account_id` | akun WABA |
| `templateId` | `template_id` | ID template |
| `phoneNumberId` | `phone_number_id` | pengirim |
| `name` | `name` | nama broadcast |
| `description` | `description` | opsional |
| `scheduledAt` | `scheduled_at` | format `"YYYY-MM-DD HH:mm:ss"` |
| `requestId` | `request_id` | idempotency key |
| `variables[]` | `variables[]` | `{ name, value }` variabel global |
| `location` | `location` | untuk template LOCATION |
| `recipients[]` | `recipients[]` | `{ phoneNumber, attachment? }` |
| `recipients[].attachment` | `{ type, link, mime_type, file_name }` | media unik per penerima (header IMAGE/VIDEO/DOCUMENT) |

### Contoh — Node

```ts
const res = await client.official.broadcasts.create({
  accountId: "665f…",
  templateId: "1234567890123456",
  phoneNumberId: "123456789012345",
  name: "Broadcast Promo April",
  description: "Media unik per penerima",
  scheduledAt: "2026-04-08 10:00:00",
  requestId: "broadcast_req_20260407_001",
  variables: [{ name: "event_name", value: "Seminar April" }],
  recipients: [
    { phoneNumber: "628123456700" },
    {
      phoneNumber: "628123456701",
      attachment: {
        type: "image",
        link: "https://cdn.contoh.com/u2.png",
        mimeType: "image/png",
        fileName: "u2.png",
      },
    },
  ],
});
console.log(res.httpStatus);          // 201
console.log(res.data);                // { broadcast_id, ... }
```

### Contoh — PHP

```php
$res = $client->official->broadcasts->create([
    'account_id'      => '665f…',
    'template_id'     => '1234567890123456',
    'phone_number_id' => '123456789012345',
    'name'            => 'Broadcast Promo April',
    'scheduled_at'    => '2026-04-08 10:00:00',
    'request_id'      => 'broadcast_req_20260407_001',
    'variables'       => [['name' => 'event_name', 'value' => 'Seminar April']],
    'recipients'      => [
        ['phone_number' => '628123456700'],
        ['phone_number' => '628123456701', 'attachment' => [
            'type' => 'image', 'link' => 'https://cdn.contoh.com/u2.png',
            'mime_type' => 'image/png', 'file_name' => 'u2.png',
        ]],
    ],
]);
```

### Contoh — Python

```python
res = client.official.broadcasts.create(
    account_id="665f…",
    template_id="1234567890123456",
    phone_number_id="123456789012345",
    name="Broadcast Promo April",
    scheduled_at="2026-04-08 10:00:00",
    request_id="broadcast_req_20260407_001",
    variables=[{"name": "event_name", "value": "Seminar April"}],
    recipients=[
        {"phone_number": "628123456700"},
        {"phone_number": "628123456701", "attachment": {
            "type": "image", "link": "https://cdn.contoh.com/u2.png",
            "mime_type": "image/png", "file_name": "u2.png",
        }},
    ],
)
```

### `list` & `monitoring`

```ts
const list = await client.official.broadcasts.list({ page: 1, limit: 10, status: "completed" });
const mon = await client.official.broadcasts.monitoring(list.data[0].id);
// mon.data: statistik pengiriman, progress, funnel, analisis kegagalan
```

```php
$list = $client->official->broadcasts->list(['page' => 1, 'limit' => 10, 'status' => 'completed']);
$mon  = $client->official->broadcasts->monitoring($broadcastId);
```

```python
lst = client.official.broadcasts.list(page=1, limit=10, status="completed")
mon = client.official.broadcasts.monitoring(broadcast_id)
```

Query `list`: `page`, `limit`, `status`, `search`.

---

## 7.5 OTP via Shared WABA serial token

Untuk pengiriman OTP lewat nomor WABA bersama (shared), memakai *serial token*.

| SDK | Wire body |
|-----|-----------|
| `sendOtp(serialToken, { to, otpCode?, metadata? })` | `{ to, otp_code?, metadata? }` |

- `to` minimal 8 karakter.
- `otpCode` opsional — bila kosong, server yang membangkitkan.
- `metadata` objek bebas (opsional).

```ts
// Node
const otp = await client.official.sharedWaba.sendOtp("srl_abcdef123456", {
  to: "6281234567890",
  otpCode: "123456",
  metadata: { source: "checkout" },
});
console.log(otp.data);   // { message_id, otp_code, serial_token }
```

```php
// PHP
$otp = $client->official->sharedWaba->sendOtp('srl_abcdef123456', [
    'to' => '6281234567890',
    'otp_code' => '123456',
    'metadata' => ['source' => 'checkout'],
]);
```

```python
# Python
otp = client.official.shared_waba.send_otp(
    "srl_abcdef123456",
    to="6281234567890",
    otp_code="123456",
    metadata={"source": "checkout"},
)
```

---

## 7.6 Error khas

| Situasi | Error |
|---------|-------|
| `phone_number_id` tidak ditemukan | `NotFoundError` (404) |
| `template.name` tidak sesuai `^[a-z0-9_]+$` | `ValidationError` (422) |
| Meta menolak (template tidak approved, dsb.) | `BadRequestError` (400) — pesan diteruskan apa adanya dari Meta |
| Lebih dari 1000 penerima untuk satu tanggal | `RateLimitError` (429) |
| Scope official belum dimiliki | `PermissionError` (403) |

---

[← Accounts & References](./06-accounts-and-references.md) · [Credential Management →](./08-credential-management.md)
