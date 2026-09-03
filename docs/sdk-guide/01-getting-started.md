# 1. Instalasi & Quick Start

[← Daftar Isi](./README.md) · [Autentikasi →](./02-authentication.md)

---

## 1.1 Prasyarat

1. **API key + secret key** yang sudah di-*approve* oleh admin Plasgos.
   Belum punya? Lihat [Autentikasi §2.5](./02-authentication.md#25-cara-memperoleh-kredensial)
   atau [Credential Management](./08-credential-management.md).
2. Paket langganan yang menyertakan fitur **API** (kalau tidak, request akan
   ditolak `403`).
3. Runtime:
   - Node.js **18+**
   - PHP **8.1+** (ext-json, ext-hash)
   - Python **3.9+**

---

## 1.2 Instalasi

### Node.js / TypeScript

```bash
npm install plasgos-crm-sdk
# atau: pnpm add plasgos-crm-sdk / yarn add plasgos-crm-sdk
```

Tidak ada dependency runtime (memakai `fetch` & `crypto` bawaan Node 18+).
Type definitions sudah termasuk.

### PHP

```bash
composer require plasgos/crm-sdk
```

Default transport memakai Guzzle; bisa diganti PSR-18 client apa pun
(lihat [Konfigurasi §3.4](./03-configuration.md#34-transport-http-kustom)).

### Python

```bash
pip install plasgos-crm-sdk
```

Satu dependency: `httpx`.

---

## 1.3 Contoh pertama — kirim pesan WhatsApp

### Node.js / TypeScript

```ts
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const client = new PlasgosCrmClient(
  process.env.PLASGOS_API_KEY!,
  process.env.PLASGOS_SECRET_KEY!,
  { baseUrl: "sandbox" },
);

const res = await client.messages.send({
  channel: "whatsapp",
  account: { accountId: "665f1c2e9a1b2c3d4e5f6a7b" },
  receiver: { phoneNumber: "6281234567890", name: "Budi" },
  content: { type: "text", data: { text: "Halo dari Plasgos CRM SDK" } },
});

console.log(res.httpStatus);          // 200
console.log(res.messageId);           // id yang dikirim (di-generate bila kosong)
console.log(res.data);                // resource pesan
```

### PHP

```php
<?php
require 'vendor/autoload.php';

use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Enum\BaseUrl;

$client = new Client(
    getenv('PLASGOS_API_KEY'),
    getenv('PLASGOS_SECRET_KEY'),
    new ClientConfig(baseUrl: BaseUrl::Sandbox),
);

$res = $client->messages->send([
    'channel'    => 'whatsapp',
    'account_id' => '665f1c2e9a1b2c3d4e5f6a7b',
    'receiver'   => ['phone_number' => '6281234567890', 'name' => 'Budi'],
    'content'    => ['type' => 'text', 'data' => ['text' => 'Halo dari Plasgos CRM SDK']],
]);

echo $res->httpStatus, PHP_EOL;                 // 200
echo $res->metadata['message_id'], PHP_EOL;     // id yang dikirim
var_dump($res->data);
```

### Python

```python
import os
from plasgos_crm import PlasgosCrmClient

client = PlasgosCrmClient(
    os.environ["PLASGOS_API_KEY"],
    os.environ["PLASGOS_SECRET_KEY"],
    base_url="sandbox",
)

res = client.messages.send(
    channel="whatsapp",
    account_id="665f1c2e9a1b2c3d4e5f6a7b",
    receiver={"phone_number": "6281234567890", "name": "Budi"},
    content={"type": "text", "data": {"text": "Halo dari Plasgos CRM SDK"}},
)

print(res.http_status)              # 200
print(res.metadata["message_id"])   # id yang dikirim
print(res.data)

client.close()   # atau: with PlasgosCrmClient(...) as client: ...
```

---

## 1.4 Konsep inti

### Envelope

Setiap panggilan sukses mengembalikan objek/`dataclass` **`Envelope`** yang
sudah menyeragamkan berbagai bentuk respons server:

| Field | Tipe | Arti |
|-------|------|------|
| `status` / `status` | number \| bool | Status yang dilaporkan body, atau HTTP status |
| `message` | string \| null | Pesan dari server |
| `data` | any | Payload utama |
| `metadata` / `metadata` | object | `metadata` atau `meta` dari server, atau `{}` |
| `raw` | any | Body mentah hasil parse |
| `httpStatus` / `http_status` | number | HTTP status sebenarnya |

Detail: [Response Envelope & Error](./04-responses-and-errors.md).

### Error bertipe

Respons non-2xx melempar subclass dari `PlasgosApiError`
(mis. `ValidationError`, `RateLimitError`, `SignatureExpiredError`).
Detail & tabel mapping: [halaman 4](./04-responses-and-errors.md).

### Signing otomatis

Untuk endpoint `/api/v1/*`, SDK menghitung `x-timestamp` & `x-signature`
**setiap request** dari `apiKey` + `secretKey`. Anda tidak perlu menyentuh
header ini. Detail: [Autentikasi](./02-authentication.md).

### Namespace resource

```
client.messages.*            client.official.accounts.*
client.accounts.*            client.official.templates.*
client.references.*          client.official.messages.*
                             client.official.broadcasts.*
                             client.official.sharedWaba.*   (Python: shared_waba)
```

---

## 1.5 Menyimpan kredensial

Jangan hard-code. Gunakan environment variable / secret manager:

```bash
# .env (jangan commit)
PLASGOS_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
PLASGOS_SECRET_KEY=plg_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
PLASGOS_WEBHOOK_TOKEN=rahasia-yang-anda-tentukan
```

`secret_key` hanya ditampilkan **sekali** saat approve/regenerate. Kalau hilang,
gunakan `credentials.regenerate()` (lihat [halaman 8](./08-credential-management.md)).

---

[← Daftar Isi](./README.md) · [Autentikasi →](./02-authentication.md)
