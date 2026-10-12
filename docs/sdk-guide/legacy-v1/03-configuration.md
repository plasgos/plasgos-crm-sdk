# 3. Konfigurasi Client

[← Autentikasi](./02-authentication.md) · [Response Envelope & Error →](./04-responses-and-errors.md)

---

## 3.1 Opsi lengkap

| Opsi | Default | Keterangan |
|------|---------|-----------|
| `baseUrl` / `base_url` | `production` | `"production"`, `"sandbox"`, atau URL absolut |
| `timeout` | Node/Python `15000`ms · PHP `15.0`s | Timeout per request |
| `maxRetries` / `max_retries` | `2` | Retry untuk request idempoten (GET) saja |
| `signatureTtl` / `signature_ttl` | `300` | Informasi TTL server (detik); tidak menegakkan apa pun di sisi client |
| `userAgent` / `user_agent` | `plasgos-crm-sdk-<lang>/<versi>` | Override header `User-Agent` |
| `fetch` *(Node)* / `transport`,`http_client` *(Python)* / `httpClient` *(PHP)* | bawaan | Transport kustom untuk test / runtime khusus |

> `signatureTtl` hanya dokumentatif. Yang menolak timestamp basi adalah server
> (HTTP 408). Lihat [Autentikasi §2.2](./02-authentication.md#22-clock-skew-penyebab-408-paling-umum).

---

## 3.2 Contoh per bahasa

### Node.js / TypeScript

```ts
import { PlasgosCrmClient } from "plasgos-crm-sdk";

const client = new PlasgosCrmClient(apiKey, secretKey, {
  baseUrl: "sandbox",          // atau "https://api-crm.staging.internal"
  timeout: 20_000,
  maxRetries: 3,
  userAgent: "my-app/2.4.0",
});
```

Opsi `fetch` untuk inject implementasi HTTP (test / polyfill):

```ts
new PlasgosCrmClient(apiKey, secretKey, { fetch: myFetchImpl });
```

### PHP

```php
use Plasgos\CrmSdk\{Client, ClientConfig};
use Plasgos\CrmSdk\Enum\BaseUrl;

$client = new Client($apiKey, $secretKey, new ClientConfig(
    baseUrl: BaseUrl::Sandbox,   // atau string "https://..."
    timeout: 20.0,
    maxRetries: 3,
    userAgent: 'my-app/2.4.0',
));
```

### Python

```python
from plasgos_crm import PlasgosCrmClient

client = PlasgosCrmClient(
    api_key,
    secret_key,
    base_url="sandbox",
    timeout=20.0,
    max_retries=3,
    user_agent="my-app/2.4.0",
)
```

---

## 3.3 Base URL

| Nilai | Resolusi |
|-------|----------|
| `"production"` (default) | `https://wa-client.plasgos.co.id` |
| `"sandbox"` | `https://api-crm.sandbox.plasgos.co.id` |
| URL absolut apa pun | dipakai apa adanya (trailing slash dibersihkan) |

Node juga mengekspor konstanta:

```ts
import { BASE_URLS, resolveBaseUrl } from "plasgos-crm-sdk";
BASE_URLS.production;               // "https://wa-client.plasgos.co.id"
resolveBaseUrl("sandbox");
```

PHP: `Plasgos\CrmSdk\Enum\BaseUrl::Production`, `BaseUrl::Sandbox`,
`BaseUrl::resolve($input)`.

Python: `from plasgos_crm import BASE_URLS, resolve_base_url`.

---

## 3.4 Transport HTTP kustom

### PHP — PSR-18 / PSR-17

Default memakai Guzzle. Untuk framework-agnostic, injeksikan client Anda:

```php
use Plasgos\CrmSdk\{Client, ClientConfig};

$client = new Client($apiKey, $secretKey, new ClientConfig(
    httpClient: $psr18Client,            // Psr\Http\Client\ClientInterface
    requestFactory: $psr17RequestFactory, // Psr\Http\Message\RequestFactoryInterface
    streamFactory: $psr17StreamFactory,   // Psr\Http\Message\StreamFactoryInterface
));
```

Jika salah satu factory tidak diberikan, SDK memakai `GuzzleHttp\Psr7\HttpFactory`
sebagai fallback.

### Python — httpx

```python
import httpx
from plasgos_crm import PlasgosCrmClient

# transport kustom (mis. untuk retry/proxy tingkat rendah)
client = PlasgosCrmClient(api_key, secret_key, transport=httpx.HTTPTransport(retries=1))

# atau seluruh httpx.Client sendiri (SDK tidak akan menutupnya)
my_httpx = httpx.Client(proxies="http://proxy:8080")
client = PlasgosCrmClient(api_key, secret_key, http_client=my_httpx)
```

Saat `http_client` diberikan, SDK **tidak** memanggil `close()` atas client itu.

### Node — fetch

```ts
new PlasgosCrmClient(apiKey, secretKey, {
  fetch: (url, init) => myInstrumentedFetch(url, init),
});
```

---

## 3.5 Retry & backoff

| Aturan | Nilai |
|--------|-------|
| Method yang di-retry | **GET saja** (`maxRetries > 0`) |
| Kondisi retry | `TransportError` (jaringan/timeout), `ServerError` (≥ 500), `RateLimitError` (429) |
| Backoff | Eksponensial `500ms · 2^attempt`, dengan *full jitter*, dibatasi `8s` |
| POST | **Tidak pernah** di-retry otomatis |

`POST` (kirim pesan, broadcast) tidak di-retry karena berpotensi duplikasi.
Buat retry idempoten sendiri memakai `message_id` / `request_id`
(lihat [Recipes §10.1](./10-recipes.md#101-retry-idempoten)).

Menonaktifkan retry:

```ts
new PlasgosCrmClient(apiKey, secretKey, { maxRetries: 0 });
```

---

## 3.6 Timeout

Timeout berlaku per attempt. Saat terlampaui → `TransportError`
(pesan memuat "timed out"). Untuk operasi berat (broadcast besar), naikkan:

```python
PlasgosCrmClient(api_key, secret_key, timeout=60.0)
```

---

## 3.7 Menutup client (Python)

`httpx.Client` menahan koneksi. Tutup saat selesai:

```python
client = PlasgosCrmClient(api_key, secret_key)
try:
    ...
finally:
    client.close()

# atau context manager
with PlasgosCrmClient(api_key, secret_key) as client:
    ...
```

Node & PHP tidak butuh penutupan eksplisit.

---

[← Autentikasi](./02-authentication.md) · [Response Envelope & Error →](./04-responses-and-errors.md)
