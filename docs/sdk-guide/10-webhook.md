# 10. Webhook

Plasgos CRM mengirim event ke URL Anda, ditandatangani HMAC.

## Langganan

```ts
const created = await client.webhooks.create({
  url: "https://toko.example/webhooks/plasgos",
  events: ["message.received", "message.status_updated", "lead.created"],
  description: "Toko utama",
});
const signingSecret = created.data.signing_secret;   // HANYA muncul di sini — simpan (terenkripsi)
await client.webhooks.test(created.data.id);          // kirim event webhook.test sekarang
```

```php
$created = $client->webhooks->create(['url' => 'https://toko.example/webhooks/plasgos', 'events' => ['message.received']]);
$secret = $created->data['signing_secret'];
```

```python
created = client.webhooks.create({"url": "https://toko.example/webhooks/plasgos", "events": ["message.received"]})
secret = created.data["signing_secret"]
```

Aturan: URL wajib `https` dan beralamat publik; maks. 5 langganan per tenant. `update(id, changes)`
(mis. `enabled`, `events`, `url`), `delete(id)`, `list()`.

| Event | Kapan |
|---|---|
| `message.received` | pesan masuk dari pelanggan |
| `message.status_updated` | status pesan keluar berubah (sent, delivered, read, failed) |
| `contact.created`, `contact.updated` | kontak dibuat / diubah |
| `lead.created`, `lead.stage_changed` | lead dibuat / pindah stage |
| `deal.won`, `deal.lost` | deal dimenangkan / kalah |
| `broadcast.completed` | broadcast selesai |
| `followup.enrollment.completed`, `followup.enrollment.stopped` | pendaftaran followup selesai / dihentikan |
| `webhook.test` | dari `webhooks.test(id)` |

Bentuk event: `{ id, type, api_version: "v3", created_at, data }` — `data` berbentuk sama dengan
respons GET v3 (mis. `Message`, `Contact`, `Lead`).

## Verifikasi

```
Plasgos-Webhook-Id, Plasgos-Webhook-Timestamp
Plasgos-Webhook-Signature = "v1=" + hex(hmac_sha256(signing_secret, id + "." + timestamp + "." + raw_body))
```

Verifikasi **body mentah** (bukan JSON yang di-serialisasi ulang), tolak yang lewat 5 menit, dan buang
`id` yang sudah pernah diproses — pengiriman bisa berulang.

```ts
import express from "express";
import { Webhook, WebhookEventType } from "plasgos-crm-sdk";

app.post("/webhooks/plasgos", express.text({ type: () => true }), (req, res) => {
  try {
    const event = Webhook.verify(req.body, req.headers, signingSecret);
    if (event.type === WebhookEventType.MESSAGE_RECEIVED) { /* event.data adalah Message */ }
    res.sendStatus(200);
  } catch { res.sendStatus(401); }
});
```

```php
use Plasgos\CrmSdk\Webhook\EventType;
use Plasgos\CrmSdk\Webhook\Webhook;

$event = Webhook::verify(file_get_contents('php://input'), getallheaders(), $signingSecret);
// PSR-7: Webhook::verifyRequest($serverRequest, $signingSecret);
if ($event['type'] === EventType::MESSAGE_RECEIVED) { /* … */ }
```

```python
from plasgos_crm import Webhook, WebhookEventType, WebhookVerificationError

@app.post("/webhooks/plasgos")
def plasgos_webhook():
    try:
        event = Webhook.verify(request.get_data(), request.headers, signing_secret)   # juga WSGI environ / Django META
    except WebhookVerificationError:
        return "", 401
    return "", 200
```

Balas **2xx secepatnya** lalu proses di latar. Kegagalan dicoba ulang server 8× (sekitar 23,6 jam,
body sama); langganan dinonaktifkan setelah 3 hari terus gagal.

## Rotasi secret

```ts
const rotated = await client.webhooks.rotateSecret(id);   // PHP: rotateSecret, Python: rotate_secret
// secret lama tetap sah 24 jam; selama itu header membawa dua tanda tangan
Webhook.verify(rawBody, headers, [rotated.data.signing_secret, previousSecret]);
```
