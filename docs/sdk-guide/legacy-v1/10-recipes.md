# 10. Recipes & Best Practice

[← Webhooks](./09-webhooks.md) · [Daftar Isi →](./README.md)

---

## 10.1 Retry idempoten

SDK tidak me-retry `POST` otomatis. Bungkus sendiri memakai kunci idempotensi
bawaan endpoint:

| Operasi | Kunci idempotensi |
|---------|-------------------|
| `messages.send` | `message_id` (unik global; duplikat → `409`) |
| `official.broadcasts.create` | `request_id` |

Pola: pakai key yang **deterministik dari kejadian bisnis** (mis.
`order-INV-001-shipped`), lalu perlakukan `409` sebagai sukses.

### Node

```ts
import { ConflictError, TransportError, ServerError, RateLimitError } from "plasgos-crm-sdk";

async function sendOnce(input: Parameters<typeof client.messages.send>[0], tries = 4) {
  for (let attempt = 0; ; attempt++) {
    try {
      return await client.messages.send(input);
    } catch (err) {
      if (err instanceof ConflictError) return err;          // sudah terkirim sebelumnya
      const retryable =
        err instanceof TransportError || err instanceof ServerError || err instanceof RateLimitError;
      if (!retryable || attempt >= tries - 1) throw err;
      await new Promise(r => setTimeout(r, 500 * 2 ** attempt + Math.random() * 250));
    }
  }
}

await sendOnce({
  messageId: `order-${orderId}-shipped`,
  channel: "whatsapp",
  account: { accountId: ACCOUNT_ID },
  receiver: { phoneNumber, name },
  content: { type: "text", data: { text: "Pesanan Anda dikirim." } },
});
```

### Python

```python
import random, time
from plasgos_crm import ConflictError, TransportError, ServerError, RateLimitError

def send_once(**kwargs):
    tries = 4
    for attempt in range(tries):
        try:
            return client.messages.send(**kwargs)
        except ConflictError as e:
            return e                       # sudah terkirim
        except (TransportError, ServerError, RateLimitError):
            if attempt == tries - 1:
                raise
            time.sleep(0.5 * 2 ** attempt + random.random() * 0.25)

send_once(
    message_id=f"order-{order_id}-shipped",
    channel="whatsapp",
    account_id=ACCOUNT_ID,
    receiver={"phone_number": phone, "name": name},
    content={"type": "text", "data": {"text": "Pesanan Anda dikirim."}},
)
```

### PHP

```php
use Plasgos\CrmSdk\Exception\{ConflictException, TransportException, ServerException, RateLimitException};

function sendOnce(\Plasgos\CrmSdk\Client $client, array $input, int $tries = 4) {
    for ($attempt = 0; ; $attempt++) {
        try {
            return $client->messages->send($input);
        } catch (ConflictException $e) {
            return $e;                                  // sudah terkirim
        } catch (TransportException|ServerException|RateLimitException $e) {
            if ($attempt >= $tries - 1) throw $e;
            usleep((int) ((0.5 * (2 ** $attempt) + mt_rand(0, 250) / 1000) * 1_000_000));
        }
    }
}
```

---

## 10.2 Pagination

Endpoint paginated: `references.contacts`, `official.broadcasts.list`,
`credentials.messages`. `metadata` (atau `meta`) berisi
`page`, `limit`, `total`, `total_pages`.

### Generator (Node)

```ts
async function* allContacts(client, pageSize = 100) {
  for (let page = 1; ; page++) {
    const res = await client.references.contacts({ page, limit: pageSize });
    yield* res.data;
    const totalPages = Number(res.metadata.total_pages ?? 1);
    if (page >= totalPages) break;
  }
}

for await (const contact of allContacts(client)) handle(contact);
```

### Iterator (Python)

```python
def iter_contacts(client, page_size=100):
    page = 1
    while True:
        res = client.references.contacts(page=page, limit=page_size)
        yield from res.data
        if page >= int(res.metadata.get("total_pages", 1)):
            break
        page += 1

for contact in iter_contacts(client):
    handle(contact)
```

---

## 10.3 Bulk kirim pesan (rate-aware)

Batasi konkurensi dan hormati `RateLimitError` (429).

### Node — batas konkurensi sederhana

```ts
import { RateLimitError } from "plasgos-crm-sdk";

async function sendBulk(items: SendInput[], concurrency = 5) {
  const queue = [...items];
  const results: unknown[] = [];
  async function worker() {
    while (queue.length) {
      const item = queue.shift()!;
      try {
        results.push(await client.messages.send(item));
      } catch (err) {
        if (err instanceof RateLimitError) {
          queue.unshift(item);              // kembalikan ke antrean
          await new Promise(r => setTimeout(r, 60_000));
        } else {
          results.push({ error: err, item });
        }
      }
    }
  }
  await Promise.all(Array.from({ length: concurrency }, worker));
  return results;
}
```

Untuk broadcast massal ke WABA official, **pakai `official.broadcasts.create`**
(satu panggilan, hingga 1000 penerima/hari) alih-alih loop `sendTemplate`.

---

## 10.4 Timeout & retry untuk operasi berat

```python
# broadcast besar: perpanjang timeout, matikan retry (POST tak idempoten tanpa request_id unik)
client = PlasgosCrmClient(api_key, secret_key, timeout=60.0, max_retries=0)
```

```ts
const heavy = new PlasgosCrmClient(apiKey, secretKey, { timeout: 60_000, maxRetries: 0 });
```

---

## 10.5 Testing / mocking (tanpa memanggil server sungguhan)

### Node — inject `fetch`

```ts
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const fakeFetch = async (url: string, init: RequestInit) =>
  new Response(JSON.stringify({ status: 200, message: "ok", data: [], metadata: {} }), {
    status: 200, headers: { "content-type": "application/json" },
  });

const client = new PlasgosCrmClient("k", "s", { baseUrl: "sandbox", fetch: fakeFetch as any, maxRetries: 0 });
```

### Python — `httpx.MockTransport`

```python
import httpx
from plasgos_crm import PlasgosCrmClient

def handler(request: httpx.Request) -> httpx.Response:
    assert request.headers["x-signature"]
    return httpx.Response(200, json={"status": 200, "message": "ok", "data": [], "metadata": {}})

client = PlasgosCrmClient("k", "s", base_url="sandbox", transport=httpx.MockTransport(handler))
```

### PHP — PSR-18 client palsu

```php
use Plasgos\CrmSdk\{Client, ClientConfig};
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

$fake = new class implements ClientInterface {
    public array $requests = [];
    public function sendRequest(RequestInterface $request): ResponseInterface {
        $this->requests[] = $request;
        return new Response(200, ['Content-Type' => 'application/json'],
            json_encode(['status' => 200, 'message' => 'ok', 'data' => [], 'metadata' => []]));
    }
};

$client = new Client('k', 's', new ClientConfig(httpClient: $fake));
```

---

## 10.6 Logging & observability

- Semua error SDK mengekspos `.httpStatus` / `->httpStatus` / `.http_status`
  dan `.raw` — log keduanya.
- `.requestId` (bila server mengirim `x-request-id`) memudahkan korelasi saat
  melaporkan masalah ke tim Plasgos.
- Set `userAgent` unik per aplikasi/versi agar mudah dilacak di sisi server.

```ts
new PlasgosCrmClient(apiKey, secretKey, { userAgent: "billing-worker/1.4.2" });
```

---

## 10.7 Checklist produksi

- [ ] Kredensial di secret manager, bukan di kode/repo.
- [ ] `baseUrl: "production"`.
- [ ] Jam server disinkron NTP (hindari 408).
- [ ] `permissions` API key seminimal mungkin.
- [ ] `message_id` / `request_id` deterministik untuk retry aman.
- [ ] Endpoint webhook: balas < 10s, proses async, idempoten, pakai `verify_token`.
- [ ] Tangani `RateLimitError` dengan backoff/antrean.
- [ ] Log `httpStatus` + `raw` + `requestId` pada setiap kegagalan.
- [ ] Rotasi `secret_key` berkala via `credentials.regenerate()`.

---

[← Webhooks](./09-webhooks.md) · [Daftar Isi →](./README.md)
