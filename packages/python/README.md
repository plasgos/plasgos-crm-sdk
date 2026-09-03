# plasgos-crm-sdk (Python)

Official Python SDK for the **Plasgos CRM — Api Integration** API.

- One dependency (`httpx`).
- Automatic HMAC request signing.
- Normalised response envelope, typed exceptions.
- Webhook verification helper.
- Separate client for the credential-management endpoints.

## Install

```bash
pip install plasgos-crm-sdk
```

## Quick start (signed API)

```python
from plasgos_crm import PlasgosCrmClient

client = PlasgosCrmClient(
    api_key="...",
    secret_key="...",
    base_url="production",  # "production" | "sandbox" | absolute URL
)

env = client.messages.send(
    channel="whatsapp",
    account_id="665f1c2e9a1b2c3d4e5f6a7b",
    receiver={"phone_number": "628123456789", "name": "John Doe"},
    content={"type": "text", "data": {"text": "Halo dari SDK"}},
)
print(env.metadata["message_id"], env.data)

accounts = client.accounts.list()

client.official.messages.send_template(
    phone_number_id="123456789012345",
    to="6281234567890",
    template={
        "id": "1234567890123456",
        "name": "order_update_v1",
        "category": "UTILITY",
        "parameters": {"variables": [{"type": "custom", "name": "1", "value": "Budi"}]},
    },
)

client.close()  # or: with PlasgosCrmClient(...) as client: ...
```

Every call returns an `Envelope`:

```python
@dataclass(frozen=True)
class Envelope:
    status: int | bool
    message: str | None
    data: Any
    metadata: dict
    raw: Any
    http_status: int
```

## Errors

```python
from plasgos_crm import ValidationError, RateLimitError, SignatureExpiredError

try:
    client.messages.send(...)
except ValidationError as e:
    print(e.errors)
except RateLimitError:
    ...
except SignatureExpiredError:
    ...  # local clock skew vs. server
```

Hierarchy: `PlasgosApiError` → `AuthenticationError`, `PermissionError`
(`PlanError`), `NotFoundError`, `SignatureExpiredError`, `ConflictError`,
`ValidationError`, `BadRequestError`, `RateLimitError`, `ServerError`,
`TransportError`.

> An un-approved API key can surface as HTTP 500 (`ServerError`) with a message
> containing `"You don't have permission"` — a known server quirk.

## Credential management (`/v2/integration/*`)

```python
from plasgos_crm import PlasgosCrmCredentialsClient

creds = PlasgosCrmCredentialsClient(access_token, fingerprint, base_url="production")
creds.credentials.request()
current = creds.credentials.get()
creds.credentials.regenerate()
creds.credentials.subscribe_webhook(
    webhook_url="https://your.app/webhooks/plasgos",
    verify_token="a-shared-secret",
)
```

## Webhooks

```python
from plasgos_crm import verify_webhook, MESSAGE_RECEIVED

event = verify_webhook(request.headers, raw_body, expected_token="a-shared-secret")
if event["event"] == MESSAGE_RECEIVED:
    ...
```

`verify_webhook` compares the `x-hub-token` header (constant-time) and parses
the JSON body. There is no payload HMAC.

## Configuration

| Argument | Default | |
|---|---|---|
| `base_url` | `"production"` | `"production"`, `"sandbox"`, or an absolute URL |
| `timeout` | `15.0` | seconds |
| `max_retries` | `2` | idempotent GET only; network / 5xx / 429 |
| `user_agent` | `plasgos-crm-sdk-python/<version>` | |
| `transport` / `http_client` | — | inject `httpx.MockTransport` / `httpx.Client` for tests |

## Development

```bash
pip install -e ".[dev]"
pytest
mypy src
ruff check .
```
