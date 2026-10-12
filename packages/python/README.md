# plasgos-crm-sdk (Python)

Official Python SDK for **Plasgos CRM: API Integrasi v3**. The SDK covers:

- omnichannel messages (WhatsApp official and unofficial, Instagram, Messenger)
- contacts and leads
- broadcasts
- followups
- signed webhooks

Features:

- Python 3.9+, one dependency (`httpx`), fully typed (`py.typed`, mypy strict).
- Automatic request signing that covers the method, path, query and body.
- An automatic `Idempotency-Key` on every POST, reused on retries.
- Typed errors carrying the API `error_code`.
- A `Page` type with `auto_paging()`.
- Webhook HMAC verification.

> Upgrading from 1.x? See [UPGRADING.md](../../UPGRADING.md). The v1 client lives on as the
> deprecated `LegacyClient` (`PlasgosCrmClient` is an alias); its docs are in [LEGACY_V1.md](LEGACY_V1.md).

## Install

```bash
pip install plasgos-crm-sdk
```

## Quick start

```python
import os

from plasgos_crm import Client, Content, PlasgosApiError

with Client(
    os.environ["PLASGOS_API_KEY"],
    os.environ["PLASGOS_SECRET_KEY"],
    base_url="sandbox",  # "production" by default; or an absolute URL
) as client:
    me = client.me.get()
    print(me.data["tenant"]["name"])

    try:
        sent = client.messages.send(
            {
                "endpoint_id": "6281100000001",
                "recipient": {"phone_number": "081234567890"},
                "create_contact": {"name": "Budi Santoso"},
                "content": Content.template("66f1a2b3c4d5e6f708192a3b")
                .dynamic("name", "{{full_name}}")
                .custom("order", "INV-2026-0917"),
            },
            idempotency_key="wc-1234-processing",  # one per intent
        )
        print(sent.data["status"])  # pending | sent | failed (202)
    except PlasgosApiError as error:
        # Branch on the stable code; show error.message (Indonesian) to people.
        if error.error_code == "requires_template":
            ...
        raise

    for contact in client.contacts.list(per_page=100).auto_paging():
        ...  # every page fetched lazily

    client.contacts.upsert({"phone_number": "081234567890", "name": "Budi", "tags": ["woocommerce"]})
    client.leads.create({"contact": {"phone_number": "081234567890"}, "pipeline_id": "pl_1"})
    client.followups.enroll("fu_1", {"contact_id": "ct_1"})
```

Resources:

- `me`, `channels`
- `endpoints` (including `templates`), `template_variables`
- `messages` (`send_options`, `send`, `get`)
- `contacts`, `contact_groups`
- `pipelines`, `leads`
- `broadcasts`, `followups`
- `webhooks` (including `rotate_secret`)

Query parameters may be a mapping, keyword arguments, or both:
`client.contacts.list({"tag": "vip"}, per_page=100)`.
`client.request(method, path, query=None, body=None, idempotency_key=None)` is an escape hatch for
endpoints that do not have a method yet. The full surface is in
[docs/SDK_SPEC.md](../../docs/SDK_SPEC.md) §3. Inputs and outputs use the API wire format
(snake_case), described by [openapi/openapi-v3.json](../../openapi/openapi-v3.json).

## Options

| Argument | Default | Meaning |
|---|---|---|
| `base_url` | `"production"` | `"production"`, `"sandbox"`, or an absolute URL (host only). |
| `timeout` | `15.0` s | Per-request timeout. |
| `max_retries` | `2` | Automatic retries (see below). |
| `max_retry_wait` | `30.0` s | Longest `Retry-After` honoured automatically. |
| `user_agent` | `plasgos-crm-sdk-python/<version> python/<version>` | Integrations append themselves. |
| `transport` / `http_client` | — | Inject an `httpx` transport (e.g. `httpx.MockTransport`) or your own `httpx.Client`. |
| `sleeper`, `clock` | real | Test hooks. |

## Responses

```python
r.http_status  # 200, 201, 202 …
r.message      # user-facing text
r.data         # payload
r.meta         # e.g. {"operation": "created"} for upserts
r.request_id   # quote it when contacting Plasgos
r.rate_limit   # RateLimit(limit=120, remaining=119, reset=42)
r.replayed     # True when the server replayed the first response for this Idempotency-Key
```

List methods return a `Page`: `items`, `pagination`, `has_next_page()`, `next_page()`,
`auto_paging()` (generator); it is iterable over the current page and supports `len()`.

## Errors

```
PlasgosApiError
├─ AuthenticationError            401
│  └─ SignatureExpiredError       401 signature_expired (check the server clock)
├─ PermissionError                403 (scope_missing, api_key_revoked, …)
│  └─ PlanError                   403 not_entitled
├─ NotFoundError                  404
├─ ConflictError                  409 (idempotency_in_progress, identity_taken, …)
├─ BadRequestError                400
├─ ValidationError                422 / 400 with fields → .fields
├─ RateLimitError                 429 → .retry_after (rate_limited | daily_quota_exceeded)
├─ ServerError                    5xx
│  ├─ ChannelError                502 provider error
│  └─ ServiceUnavailableError     503
└─ TransportError                 no response
```

Every error exposes `error_code`, `error_type`, `details`, `fields`, `doc_url`, `request_id` and
`retry_after` (plus the legacy `errors` view `[{message, path}]`). `PermissionError` deliberately
shadows the builtin inside this package; import it as `from plasgos_crm import PermissionError as
PlasgosPermissionError` if that bothers you.

**Retries.** Transport errors, 502/503 and `rate_limited` (for any method) are retried
automatically up to `max_retries`, reusing the same idempotency key. A plain 500 is retried only for
GET. Other 4xx errors and `daily_quota_exceeded` are never retried.

## Webhooks

```python
from plasgos_crm import Webhook, WebhookEventType, WebhookVerificationError

# Flask
@app.post("/webhooks/plasgos")
def plasgos_webhook():
    try:
        event = Webhook.verify(request.get_data(), request.headers, signing_secret)
        # during secret rotation: Webhook.verify(body, headers, [new_secret, previous_secret])
    except WebhookVerificationError:
        return "", 401
    if event["type"] == WebhookEventType.MESSAGE_RECEIVED:
        ...  # event["data"] is a Message
    return "", 200
```

`headers` may be any mapping, including a WSGI `environ` or Django `request.META` (`HTTP_*` keys).
Verify the **raw** body. Deliveries can repeat, so drop `event["id"]` values you have already
processed.

## Development

```bash
pip install -e ".[dev]"
pytest              # tests/test_v3_live.py runs only with PLASGOS_LIVE_BASE_URL/_API_KEY/_SECRET_KEY
mypy src
ruff check .
```
