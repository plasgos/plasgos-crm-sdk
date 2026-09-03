# 9. Webhooks

[← Credential Management](./08-credential-management.md) · [Recipes & Best Practice →](./10-recipes.md)

---

Plasgos CRM mendorong (push) event ke `webhook_url` yang Anda daftarkan
(lihat [§8.6](./08-credential-management.md#86-subscribewebhook--daftarkan-endpoint-webhook)).

| Karakteristik | Nilai |
|---------------|-------|
| Metode | `POST` JSON |
| Header identitas | `x-hub-token: <verify_token>` |
| Signature payload | **Tidak ada HMAC** — verifikasi hanya membandingkan `x-hub-token` |
| Timeout server → Anda | 10 detik |
| Retry | 3× dengan exponential backoff (jeda dasar 5s) |
| Event publik | `message.received`, `message.statuses` |

Endpoint Anda harus membalas **2xx** secepatnya. Balasan non-2xx / timeout
memicu retry; setelah 3 kegagalan, event di-*drop* (dan tim Plasgos dapat
notifikasi kegagalan internal).

---

## 9.1 Dua hal yang harus endpoint Anda tangani

### a. Verifikasi (GET) — hanya saat `subscribeWebhook`

```
GET <webhook_url>?verify_token=<token>
Header: x-hub-token: <token>
```

Balas `200` + kembalikan token di body:

```json
{ "verify_token": "<token>" }
```

(Field boleh `verify_token`, `token`, atau `challenge`; boleh nested di `data`.)

### b. Penerimaan event (POST) — setiap kali ada event

```
POST <webhook_url>
Header: x-hub-token: <token>
Body: { ...payload event... }
```

Verifikasi `x-hub-token`, proses, balas `200`.

---

## 9.2 Struktur payload event

```json
{
  "event": "message.received",
  "event_name": "message.received",
  "event_version": 1052,
  "event_at": "2026-04-08T10:32:15.000Z",
  "timestamp": 1775644335,
  "source": "crm_chatroom",
  "channel": "whatsapp",
  "provider": "official",
  "user": { "id": 42 },
  "account": { "phone_number_id": "123456789012345" },
  "conversation": {
    "conversation_id": "conv_01HRCHATROOMABC",
    "phone_number_id": "123456789012345",
    "conversation_status": "open"
  },
  "message": {
    "id": "wamid.HBgLN...",
    "type": "text",
    "direction": "inbound",
    "status": "unread",
    "content": { "type": "text", "text": "Halo admin" }
  },
  "statuses": null,
  "recipient": null,
  "meta": {
    "room": "socketio:user:chat_room:123456789012345",
    "emitted_via": "socket_and_webhook",
    "source_event": "chat_room:new_message"
  },
  "data": { "conversation": {}, "message": {} }
}
```

| Field | Selalu ada | Catatan |
|-------|-----------|---------|
| `event` / `event_name` | ya | `message.received` \| `message.statuses` |
| `event_version` | ya (bisa `null`) | versi skema |
| `event_at` | ya | ISO-8601 |
| `timestamp` | ya | Unix detik |
| `source` | ya | mis. `crm_chatroom` |
| `channel` | ya | mis. `whatsapp` |
| `provider` | bisa `null` | `official` \| `unofficial` |
| `user.id` | bisa `null` | id user CRM pemilik integrasi |
| `account` / `conversation` / `message` / `statuses` / `recipient` / `meta` / `data` | bisa `null` | terisi sesuai jenis event |

### `message.received`

Pesan masuk dari pelanggan. `message` terisi (`direction: "inbound"`).

### `message.statuses`

Perubahan status pesan keluar (`sent`, `delivered`, `read`, `failed`).
`statuses` terisi.

---

## 9.3 Verifikasi dengan SDK

### Node.js / TypeScript

```ts
import { verifyWebhook, WebhookEventName } from "plasgos-crm-sdk/webhooks";
// juga tersedia dari entry utama: import { verifyWebhook } from "plasgos-crm-sdk";

const event = verifyWebhook(headers, rawBody, {
  expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN,
});

switch (event.event) {
  case WebhookEventName.MESSAGE_RECEIVED:
    // event.message, event.conversation, ...
    break;
  case WebhookEventName.MESSAGE_STATUSES:
    // event.statuses
    break;
}
```

- `headers`: objek `IncomingHttpHeaders`, `Headers`, atau `Map` (case-insensitive).
- `rawBody`: `string` atau `Buffer` — **body mentah**, bukan hasil `JSON.parse`.
- Jika `expectedToken` tidak cocok / body bukan JSON → melempar
  `WebhookVerificationError`.
- Jika `expectedToken` dihilangkan, pengecekan token dilewati (parse saja).

### PHP

```php
use Plasgos\CrmSdk\Webhook\Verifier;

$event = Verifier::verify(
    getallheaders(),                 // atau array header apa pun
    file_get_contents('php://input'),
    getenv('PLASGOS_WEBHOOK_TOKEN'),
);

if ($event['event'] === Verifier::EVENT_MESSAGE_RECEIVED) {
    // ...
}

// PSR-7:
$event = Verifier::verifyRequest($serverRequest, getenv('PLASGOS_WEBHOOK_TOKEN'));
```

Melempar `Plasgos\CrmSdk\Exception\WebhookVerificationException` bila gagal.

### Python

```python
from plasgos_crm import verify_webhook, MESSAGE_RECEIVED, MESSAGE_STATUSES
from plasgos_crm.errors import WebhookVerificationError

event = verify_webhook(request.headers, raw_body, expected_token=WEBHOOK_TOKEN)

if event["event"] == MESSAGE_RECEIVED:
    ...
elif event["event"] == MESSAGE_STATUSES:
    ...
```

- `headers`: mapping apa pun (dict, `httpx.Headers`, dst.), case-insensitive.
- `raw_body`: `str` atau `bytes`.

---

## 9.4 Integrasi framework

### Express (Node) — middleware bawaan SDK

```ts
import express from "express";
import { plasgosWebhook, WebhookEventName } from "plasgos-crm-sdk/webhooks";

const app = express();

// verifikasi (GET) saat subscribeWebhook
app.get("/webhooks/plasgos", (req, res) => {
  res.status(200).json({ verify_token: req.query.verify_token });
});

// penerimaan event (POST) — butuh body mentah
app.post(
  "/webhooks/plasgos",
  express.text({ type: () => true }),
  plasgosWebhook({ expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN }),
  (req, res) => {
    const event = (req as any).plasgosEvent;
    if (event.event === WebhookEventName.MESSAGE_RECEIVED) {
      // proses async, jangan blok respons
    }
    res.sendStatus(200);
  },
);
```

`plasgosWebhook` menaruh hasil di `req.plasgosEvent`; bila verifikasi gagal ia
membalas `401` otomatis.

### Fastify (Node)

```ts
import { verifyWebhook } from "plasgos-crm-sdk/webhooks";

fastify.post("/webhooks/plasgos", { config: { rawBody: true } }, async (req, reply) => {
  try {
    const event = verifyWebhook(req.headers, req.rawBody as string, {
      expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN,
    });
    enqueue(event);
    reply.code(200).send();
  } catch {
    reply.code(401).send();
  }
});
```

### Laravel (PHP)

```php
use Illuminate\Http\Request;
use Plasgos\CrmSdk\Webhook\Verifier;
use Plasgos\CrmSdk\Exception\WebhookVerificationException;

Route::get('/webhooks/plasgos', fn (Request $r) => response()->json([
    'verify_token' => $r->query('verify_token'),
]));

Route::post('/webhooks/plasgos', function (Request $request) {
    try {
        $event = Verifier::verify(
            $request->headers->all(),
            $request->getContent(),
            config('services.plasgos.webhook_token'),
        );
    } catch (WebhookVerificationException) {
        abort(401);
    }

    ProcessPlasgosEvent::dispatch($event);   // queue job
    return response()->noContent();          // 204/200
});
```

### FastAPI (Python)

```python
from fastapi import FastAPI, Request, Response, status
from plasgos_crm import verify_webhook
from plasgos_crm.errors import WebhookVerificationError

app = FastAPI()

@app.get("/webhooks/plasgos")
async def verify(verify_token: str = ""):
    return {"verify_token": verify_token}

@app.post("/webhooks/plasgos")
async def receive(request: Request):
    raw = await request.body()
    try:
        event = verify_webhook(request.headers, raw, expected_token=WEBHOOK_TOKEN)
    except WebhookVerificationError:
        return Response(status_code=status.HTTP_401_UNAUTHORIZED)
    background_process(event)
    return Response(status_code=status.HTTP_200_OK)
```

### Flask / Django (Python)

```python
# Flask
@app.post("/webhooks/plasgos")
def receive():
    try:
        event = verify_webhook(request.headers, request.get_data(), expected_token=WEBHOOK_TOKEN)
    except WebhookVerificationError:
        return "", 401
    enqueue(event)
    return "", 200

# Django view
def receive(request):
    try:
        event = verify_webhook(request.headers, request.body, expected_token=WEBHOOK_TOKEN)
    except WebhookVerificationError:
        return HttpResponse(status=401)
    enqueue(event)
    return HttpResponse(status=200)
```

---

## 9.5 Best practice

- **Balas cepat, proses belakangan.** Verifikasi → masukkan ke queue → balas
  `200`. Jangan lakukan kerja berat sebelum merespons (batas 10s).
- **Idempoten.** Retry berarti event yang sama bisa datang > 1×. Deduplikasi
  pakai `message.id` + `event`.
- **Simpan raw payload** untuk audit sebelum diproses.
- **Selalu pakai `verify_token`.** Tanpa itu siapa pun yang tahu URL bisa
  mengirim event palsu.
- **HTTPS wajib** dan endpoint tidak boleh di jaringan privat (ditolak saat
  subscribe).
- **Toleransi field baru.** `event_version` bisa naik; jangan gagal jika ada
  key tak dikenal.
- **Tangani urutan.** `message.statuses` bisa datang tidak berurutan; pakai
  `timestamp` untuk menentukan status terbaru.

---

## 9.6 Troubleshooting

| Gejala | Kemungkinan sebab |
|--------|-------------------|
| `subscribeWebhook` → 400 | Endpoint GET tidak balas 200 / tidak echo token, atau URL menunjuk host privat |
| Event tidak pernah datang | `webhook_url` belum ter-*subscribe*, status kredensial bukan `active`, atau endpoint menolak GET verifikasi |
| `WebhookVerificationError` di semua request | `expectedToken` tidak sama dengan `verify_token` yang didaftarkan, atau membaca body setelah di-parse (kirim raw) |
| Event dobel | Normal (retry) — buat handler idempoten |
| Retry terus walau Anda proses | Anda balas non-2xx atau > 10s; pindahkan proses ke background |

---

[← Credential Management](./08-credential-management.md) · [Recipes & Best Practice →](./10-recipes.md)
