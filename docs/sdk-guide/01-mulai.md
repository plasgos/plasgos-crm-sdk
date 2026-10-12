# 1. Mulai

## Pasang paket

```bash
npm install plasgos-crm-sdk        # Node.js 18+ (ESM & CommonJS)
composer require plasgos/crm-sdk   # PHP 8.1+
pip install plasgos-crm-sdk        # Python 3.9+
```

## Panggilan pertama

Kunci API & secret didapat dari dashboard Plasgos CRM (lihat [Autentikasi](./02-autentikasi.md)).
Simpan di variabel lingkungan, jangan di kode.

**Node.js**

```ts
import { Client } from "plasgos-crm-sdk";

const client = new Client(process.env.PLASGOS_API_KEY!, process.env.PLASGOS_SECRET_KEY!, { baseUrl: "sandbox" });
const me = await client.me.get();
console.log(me.data.tenant.name, me.data.scopes);
```

**PHP**

```php
use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\ClientConfig;
use Plasgos\CrmSdk\Enum\BaseUrl;

$client = new Client(getenv('PLASGOS_API_KEY'), getenv('PLASGOS_SECRET_KEY'), new ClientConfig(baseUrl: BaseUrl::Sandbox));
$me = $client->me->get();
echo $me->data['tenant']['name'];
```

**Python**

```python
import os
from plasgos_crm import Client

with Client(os.environ["PLASGOS_API_KEY"], os.environ["PLASGOS_SECRET_KEY"], base_url="sandbox") as client:
    me = client.me.get()
    print(me.data["tenant"]["name"], me.data["scopes"])
```

`GET /me` juga menampilkan kuota harian dan jumlah langganan webhook — cocok untuk tombol "Uji koneksi".

## Kirim pesan pertama

```ts
// Node
import { Content } from "plasgos-crm-sdk";
const sent = await client.messages.send(
  { endpoint_id: "6281100000001", recipient: { phone_number: "081234567890" }, content: Content.text("Halo!") },
  "pesan-pertama-001", // Idempotency-Key — satu per maksud kirim
);
console.log(sent.httpStatus, sent.data.status); // 202, pending | sent | failed
```

```php
// PHP
use Plasgos\CrmSdk\Content;
$sent = $client->messages->send([
    'endpoint_id' => '6281100000001',
    'recipient' => ['phone_number' => '081234567890'],
    'content' => Content::text('Halo!'),
], idempotencyKey: 'pesan-pertama-001');
```

```python
# Python
from plasgos_crm import Content
sent = client.messages.send(
    {"endpoint_id": "6281100000001", "recipient": {"phone_number": "081234567890"}, "content": Content.text("Halo!")},
    idempotency_key="pesan-pertama-001",
)
```

## Konsep inti

| Konsep | Arti |
|---|---|
| **Titik kirim (endpoint)** | Satu nomor WhatsApp (resmi/tidak resmi), satu akun Instagram, atau satu halaman Messenger milik tenant. Id-nya dari `endpoints.list()`. |
| **Penerima (recipient)** | Tepat satu dari `contact_id`, `phone_number` (WhatsApp saja), atau `identity` (`channel` + `external_id`). |
| **Isi (content)** | `text`, media (`image`/`video`/`audio`/`document`), atau `template` WhatsApp — bentuk yang sama dengan inbox CRM. Gunakan builder `Content`. |
| **Idempotency-Key** | Setiap `POST` membawanya (dibuat otomatis bila tak diberikan) dan dipakai ulang saat coba ulang, jadi tak ada kiriman ganda. Beri kunci bermakna per maksud, mis. `wc-1234-processing`. |
| **Format kabel** | Isi permintaan & `data` respons apa adanya dari API (`snake_case`); skemanya ada di OpenAPI. |
| **Kode galat** | Setiap galat punya `errorCode` (`error_code` di Python) yang stabil — bercabanglah dengan itu, tampilkan `message` (Bahasa Indonesia) ke orang. |
