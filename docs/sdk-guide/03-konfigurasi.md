# 3. Konfigurasi klien

| Opsi | Node | PHP (`ClientConfig`) | Python | Bawaan |
|---|---|---|---|---|
| Base URL | `baseUrl` | `baseUrl` | `base_url` | production |
| Timeout per permintaan | `timeout` (ms) | `timeout` (detik) | `timeout` (detik) | 15 detik |
| Coba ulang otomatis | `maxRetries` | `maxRetries` | `max_retries` | 2 |
| `Retry-After` terlama yang ditunggu | `maxRetryWait` (detik) | `maxRetryWait` | `max_retry_wait` | 30 detik |
| User-Agent | `userAgent` | `userAgent` | `user_agent` | `plasgos-crm-sdk-<bahasa>/<versi> …` |
| Transport | `fetch` | `httpClient` + `requestFactory` + `streamFactory` (PSR-18/17) | `transport` / `http_client` (httpx) | bawaan platform |
| Hook uji | `sleep`, `clock` | `sleeper`, `clock` | `sleeper`, `clock` | nyata |

`ClientConfig` PHP juga dipakai `LegacyClient` (v1), sehingga punya `signatureTtl`. Opsi ini hanya
berlaku untuk v1 dan diabaikan `Client` v3: masa berlaku tanda tangan v3 (5 menit) ditegakkan server.

## Base URL

| Nilai | URL |
|---|---|
| `production` | `https://wa-client.plasgos.co.id` |
| `sandbox` | `https://api-crm.sandbox.plasgos.co.id` |
| URL lain | host saja — SDK menambahkan `/api/v3` |

```ts
new Client(key, secret, { baseUrl: "https://crm.internal.example", timeout: 20_000, maxRetries: 3 });
```

```php
new Client($key, $secret, new ClientConfig(baseUrl: 'https://crm.internal.example', timeout: 20.0, maxRetries: 3));
```

```python
Client(key, secret, base_url="https://crm.internal.example", timeout=20.0, max_retries=3)
```

## User-Agent integrasi

Tambahkan nama integrasi Anda supaya mudah dilacak di log Plasgos:

```php
new ClientConfig(userAgent: 'toko-saya/1.4.0 (+https://toko.example) plasgos-crm-sdk-php/2.0.0');
```

## Transport kustom

- **Node**: `fetch` apa pun yang kompatibel (mis. `undici`, atau fetch tiruan untuk test).
- **PHP**: PSR-18 client + PSR-17 factory. Plugin WordPress resmi memakai adaptor di atas
  `wp_remote_request()`.
- **Python**: `transport=httpx.MockTransport(handler)` untuk test, atau `http_client=` milik Anda sendiri
  (tidak ditutup oleh `close()`).

## Endpoint tanpa metode

```ts
const r = await client.request("GET", "/contacts", { per_page: 10 });          // Node
```

```php
$r = $client->request('GET', '/contacts', ['per_page' => 10]);                 // PHP
```

```python
r = client.request("GET", "/contacts", {"per_page": 10})                      # Python
```

`request` tetap menandatangani, memberi Idempotency-Key pada POST, dan memakai kebijakan coba ulang
yang sama.

## Menutup klien (Python)

`Client` Python memegang koneksi httpx: pakai `with Client(...) as client:` atau panggil `client.close()`.
