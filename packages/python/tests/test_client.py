from __future__ import annotations

import json
import re
from typing import Any, Callable

import httpx
import pytest

from plasgos_crm import (
    LegacyClient,
    PlasgosCrmClient,
    PlanError,
    RateLimitError,
    ServerError,
    SignatureExpiredError,
    ValidationError,
)
from plasgos_crm.errors import NotFoundError

# Legacy /api/v1 client: deprecated in 2.0, still supported until 3.0.
pytestmark = pytest.mark.filterwarnings("ignore::DeprecationWarning")


def make_client(
    handler: Callable[[httpx.Request], httpx.Response], *, max_retries: int = 0
) -> LegacyClient:
    return LegacyClient(
        "ak_live_123",
        "sk_live_abc",
        base_url="sandbox",
        max_retries=max_retries,
        transport=httpx.MockTransport(handler),
    )


def json_response(body: Any, status: int = 200) -> httpx.Response:
    return httpx.Response(status, json=body)


def test_sends_signature_headers_to_sandbox_base_url() -> None:
    seen: dict[str, httpx.Request] = {}

    def handler(request: httpx.Request) -> httpx.Response:
        seen["req"] = request
        return json_response({"status": 200, "message": "ok", "data": [], "metadata": {}})

    make_client(handler).accounts.list()
    req = seen["req"]
    assert str(req.url) == "https://api-crm.sandbox.plasgos.co.id/api/v1/accounts"
    assert req.headers["x-api-key"] == "ak_live_123"
    assert re.fullmatch(r"\d+", req.headers["x-timestamp"])
    assert re.fullmatch(r"[0-9a-f]{64}", req.headers["x-signature"])


def test_maps_send_input_to_wire_body() -> None:
    seen: dict[str, Any] = {}

    def handler(request: httpx.Request) -> httpx.Response:
        seen["body"] = json.loads(request.content)
        return json_response({"status": 200, "message": "sent", "data": {}, "metadata": {}})

    env = make_client(handler).messages.send(
        message_id="m-1",
        channel="whatsapp",
        account_id="acc-1",
        receiver={"phone_number": "628123456789", "name": "John"},
        content={
            "type": "document",
            "data": {"url": "http://x/y.pdf", "mime_type": "application/pdf", "file_name": "y.pdf"},
        },
    )
    assert seen["body"] == {
        "message_id": "m-1",
        "channel": "whatsapp",
        "account": {"account_id": "acc-1"},
        "receiver": {"phone_number": "628123456789", "name": "John"},
        "content": {
            "type": "document",
            "data": {"url": "http://x/y.pdf", "mime_type": "application/pdf", "file_name": "y.pdf"},
        },
    }
    assert env.metadata["message_id"] == "m-1"


def test_generates_message_id_when_omitted() -> None:
    seen: dict[str, Any] = {}

    def handler(request: httpx.Request) -> httpx.Response:
        seen["body"] = json.loads(request.content)
        return json_response({"status": 200, "message": "sent", "data": {}, "metadata": {}})

    env = make_client(handler).messages.send(
        channel="whatsapp",
        account_id="a",
        receiver={"phone_number": "62811", "name": "n"},
        content={"type": "text", "data": {"text": "hi"}},
    )
    assert re.search(r"[0-9a-f-]{36}", seen["body"]["message_id"])
    assert env.metadata["message_id"] == seen["body"]["message_id"]


def test_normalises_success_data_envelope() -> None:
    client = make_client(
        lambda _r: json_response({"success": True, "data": [{"label": "name"}]})
    )
    env = client.references.variables()
    assert env.message is None
    assert env.data == [{"label": "name"}]
    assert env.http_status == 200


@pytest.mark.parametrize(
    "status,body,expected",
    [
        (408, {"status": 408, "message": "Request timestamp expired"}, SignatureExpiredError),
        (429, {"success": False, "message": "Batas hit API harian tercapai (100/100)"}, RateLimitError),
        (403, {"success": False, "message": "Paket langganan Anda tidak menyertakan akses API"}, PlanError),
    ],
)
def test_error_mapping(status: int, body: dict, expected: type[Exception]) -> None:
    client = make_client(lambda _r: json_response(body, status))
    with pytest.raises(expected):
        client.accounts.list()


def test_validation_error_carries_issues() -> None:
    client = make_client(
        lambda _r: json_response(
            {
                "status": 422,
                "message": "Missing or invalid data",
                "errors": [{"message": "bad phone", "path": ["receiver", "phone_number"]}],
            },
            422,
        )
    )
    with pytest.raises(ValidationError) as excinfo:
        client.messages.send(
            channel="whatsapp",
            account_id="a",
            receiver={"phone_number": "1", "name": "n"},
            content={"type": "text", "data": {"text": "hi"}},
        )
    assert excinfo.value.errors[0]["path"] == ["receiver", "phone_number"]


def test_body_status_over_http_200_still_raises() -> None:
    client = make_client(
        lambda _r: json_response({"status": 404, "message": "Account not found", "data": None}, 200)
    )
    with pytest.raises(NotFoundError) as excinfo:
        client.accounts.get("nope")
    assert excinfo.value.http_status == 404


def test_retries_idempotent_get_on_500() -> None:
    calls = {"n": 0}

    def handler(_request: httpx.Request) -> httpx.Response:
        calls["n"] += 1
        if calls["n"] == 1:
            return json_response({"status": 500, "message": "boom"}, 500)
        return json_response({"status": 200, "message": "ok", "data": [], "metadata": {}})

    env = make_client(handler, max_retries=2).accounts.list()
    assert calls["n"] == 2
    assert env.http_status == 200


def test_does_not_retry_post() -> None:
    calls = {"n": 0}

    def handler(_request: httpx.Request) -> httpx.Response:
        calls["n"] += 1
        return json_response({"status": 500, "message": "boom"}, 500)

    with pytest.raises(ServerError):
        make_client(handler, max_retries=3).messages.send(
            channel="whatsapp",
            account_id="a",
            receiver={"phone_number": "62811", "name": "n"},
            content={"type": "text", "data": {"text": "hi"}},
        )
    assert calls["n"] == 1


@pytest.mark.filterwarnings("default::DeprecationWarning")
def test_legacy_client_is_deprecated_and_keeps_old_name() -> None:
    assert PlasgosCrmClient is LegacyClient
    with pytest.warns(DeprecationWarning, match="use plasgos_crm.Client"):
        LegacyClient("k", "s", transport=httpx.MockTransport(lambda r: httpx.Response(200)))
