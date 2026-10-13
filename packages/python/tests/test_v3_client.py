from __future__ import annotations

import hashlib
import hmac
import json
import logging
import re
from pathlib import Path
from typing import Any, Union

import httpx
import pytest

from plasgos_crm import (
    AuthenticationError,
    BadRequestError,
    ChannelError,
    Client,
    ConflictError,
    Content,
    NotFoundError,
    PermissionError,
    PlanError,
    PlasgosApiError,
    RateLimitError,
    ServerError,
    ServiceUnavailableError,
    SignatureExpiredError,
    TransportError,
    ValidationError,
    build_query,
    sign_v3,
    string_to_sign,
)

REQUEST_VECTORS = json.loads(
    (Path(__file__).resolve().parents[3] / "docs" / "vectors-v3.json").read_text()
)["request"]

Reply = Union[dict[str, Any], Exception]


class Harness:
    """httpx.MockTransport double: answers from a queue and records every request."""

    def __init__(self, responses: list[Reply], **options: Any) -> None:
        self.responses = list(responses)
        self.requests: list[httpx.Request] = []
        self.sleeps: list[float] = []
        self.now = 1_700_000_000

        def sleeper(seconds: float) -> None:
            self.sleeps.append(seconds)
            self.now += 1

        self.client = Client(
            "ak_test",
            "sk_test",
            base_url="https://crm.example",
            transport=httpx.MockTransport(self._handle),
            sleeper=sleeper,
            clock=lambda: self.now,
            **options,
        )

    def _handle(self, request: httpx.Request) -> httpx.Response:
        request.read()
        self.requests.append(request)
        if not self.responses:
            raise AssertionError("no more fake responses")
        reply = self.responses.pop(0)
        if isinstance(reply, Exception):
            raise reply
        body = reply.get("body")
        text = "" if body is None else body if isinstance(body, str) else json.dumps(body)
        return httpx.Response(reply["status"], content=text.encode(), headers=reply.get("headers", {}))


def ok(data: Any = None, meta: dict[str, Any] | None = None) -> dict[str, Any]:
    return {
        "status": 200,
        "body": {"success": True, "message": "OK", "data": {} if data is None else data, "meta": meta or {"request_id": "req_1"}},
    }


def fail(status: int, code: str, kind: str = "invalid_request", headers: dict[str, str] | None = None) -> dict[str, Any]:
    return {
        "status": status,
        "headers": headers or {},
        "body": {
            "success": False,
            "message": f"Galat {code}",
            "error": {"code": code, "type": kind, "details": None, "fields": [], "doc_url": f"https://docs/{code}"},
            "meta": {"request_id": "req_err"},
        },
    }


def connect_error() -> httpx.ConnectError:
    return httpx.ConnectError("socket hang up")


# ——— signing (SDK_SPEC §2) ———


@pytest.mark.parametrize("v", REQUEST_VECTORS, ids=lambda v: v["request_target"])
def test_golden_vectors(v: dict[str, Any]) -> None:
    assert string_to_sign(v["timestamp"], v["method"], v["request_target"], v["body"]) == v["string_to_sign"]
    assert sign_v3(v["secret_key"], v["timestamp"], v["method"], v["request_target"], v["body"]) == v["signature"]


def test_signs_method_path_and_exact_body_bytes() -> None:
    h = Harness([{"status": 202, "body": {"success": True, "message": "x", "data": {"id": "m1"}, "meta": {}}}])
    h.client.messages.send({"endpoint_id": "e1", "content": Content.text("Halo https://x/y ✓")}, "key-1")
    r = h.requests[0]
    assert str(r.url) == "https://crm.example/api/v3/messages"
    assert r.content.decode() == '{"endpoint_id":"e1","content":{"type":"text","text":{"body":"Halo https://x/y ✓","preview_url":false}}}'
    sts = f"{r.headers['Plasgos-Timestamp']}\nPOST\n/api/v3/messages\n{hashlib.sha256(r.content).hexdigest()}"
    assert r.headers["Plasgos-Signature"] == "v1=" + hmac.new(b"sk_test", sts.encode(), hashlib.sha256).hexdigest()
    assert r.headers["Plasgos-Api-Key"] == "ak_test"
    assert r.headers["Idempotency-Key"] == "key-1"
    assert r.headers["Content-Type"] == "application/json; charset=utf-8"
    assert r.headers["User-Agent"].startswith("plasgos-crm-sdk-python/2.0.0 python/")


def test_signs_query_as_sent() -> None:
    h = Harness([ok([]), ok()])
    h.client.contacts.list({"search": "budi santoso", "tag": None}, ready=True, page=2, empty="")
    assert str(h.requests[0].url) == "https://crm.example/api/v3/contacts?search=budi%20santoso&ready=true&page=2"
    assert h.requests[0].url.raw_path == b"/api/v3/contacts?search=budi%20santoso&ready=true&page=2"
    assert build_query({"q": "a!b*(c)'"}) == "q=a%21b%2A%28c%29%27"
    # Reserved & unicode characters: the signed target is byte-identical to the sent one.
    h.client.request("GET", "/me", {"search": "Budi & Sari/+62 👋"})
    target = h.requests[1].url.raw_path.decode()
    body_hash = hashlib.sha256(b"").hexdigest()
    sts = f"{h.requests[1].headers['Plasgos-Timestamp']}\nGET\n{target}\n{body_hash}"
    assert h.requests[1].headers["Plasgos-Signature"] == "v1=" + hmac.new(b"sk_test", sts.encode(), hashlib.sha256).hexdigest()
    assert target == "/api/v3/me?search=Budi%20%26%20Sari%2F%2B62%20%F0%9F%91%8B"


def test_custom_base_url_sub_path_is_signed() -> None:
    h = Harness([ok()])
    h.client._http._base_url = "https://proxy.example/crm"
    h.client.me.get()
    r = h.requests[0]
    sts = f"{r.headers['Plasgos-Timestamp']}\nGET\n/crm/api/v3/me\n{hashlib.sha256(b'').hexdigest()}"
    assert r.headers["Plasgos-Signature"] == "v1=" + hmac.new(b"sk_test", sts.encode(), hashlib.sha256).hexdigest()


def test_path_segments_are_encoded() -> None:
    h = Harness([ok()])
    h.client.contacts.get("../webhooks?x=1")
    assert h.requests[0].url.raw_path == b"/api/v3/contacts/..%2Fwebhooks%3Fx%3D1"


def test_action_post_without_input_sends_empty_object() -> None:
    h = Harness([ok()])
    h.client.broadcasts.cancel("b1")
    assert h.requests[0].content == b"{}"


def test_get_sends_no_body_and_no_idempotency_key() -> None:
    h = Harness([ok()])
    h.client.me.get()
    assert h.requests[0].content == b""
    assert "Idempotency-Key" not in h.requests[0].headers
    assert "Content-Type" not in h.requests[0].headers


# ——— idempotency & retries (SDK_SPEC §6) ———


def test_post_key_reused_across_retries_timestamp_recomputed() -> None:
    h = Harness([connect_error(), fail(503, "service_unavailable", "service_unavailable"), ok()])
    h.client.contacts.upsert({"phone_number": "0812"})
    assert len(h.requests) == 3
    keys = {r.headers["Idempotency-Key"] for r in h.requests}
    assert len(keys) == 1
    assert re.match(r"^[0-9a-f-]{36}$", keys.pop())
    assert len({r.headers["Plasgos-Timestamp"] for r in h.requests}) == 3


def test_rate_limited_honours_retry_after() -> None:
    h = Harness([fail(429, "rate_limited", "rate_limit_error", {"Retry-After": "2"}), ok()])
    h.client.me.get()
    assert h.sleeps == [2.0]


def test_idempotency_in_progress_retried_with_same_key() -> None:
    h = Harness([fail(409, "idempotency_in_progress", "invalid_request_error"), ok()])
    h.client.messages.send({"endpoint_id": "e", "contact": {"id": "c"}, "content": {"type": "text", "text": {"body": "x"}}}, idempotency_key="order-1")
    assert [r.headers["Idempotency-Key"] for r in h.requests] == ["order-1", "order-1"]
    assert len(h.sleeps) == 1
    h = Harness([fail(409, "identity_taken", "invalid_request_error")])
    with pytest.raises(ConflictError):
        h.client.contacts.create({"phone_number": "0812"})
    assert len(h.requests) == 1


def test_long_retry_after_and_daily_quota_are_raised() -> None:
    h = Harness([fail(429, "rate_limited", "rate_limit_error", {"Retry-After": "120"})])
    with pytest.raises(RateLimitError) as info:
        h.client.me.get()
    assert info.value.error_code == "rate_limited"
    assert info.value.retry_after == 120
    h = Harness([fail(429, "daily_quota_exceeded", "rate_limit_error")])
    with pytest.raises(RateLimitError):
        h.client.me.get()
    assert len(h.requests) == 1


def test_plain_500_retries_reads_not_writes() -> None:
    read = Harness([fail(500, "internal_error", "api_error"), ok()])
    read.client.me.get()
    assert len(read.requests) == 2
    write = Harness([fail(500, "internal_error", "api_error")])
    with pytest.raises(ServerError):
        write.client.leads.create({"contact": {"id": "c"}})
    assert len(write.requests) == 1


def test_502_retried_for_writes_4xx_never() -> None:
    channel = Harness([fail(502, "channel_provider_error", "channel_error"), ok()])
    channel.client.messages.send({})
    assert len(channel.requests) == 2
    conflict = Harness([fail(409, "identity_taken", "conflict")])
    with pytest.raises(ConflictError):
        conflict.client.contacts.create({})
    assert len(conflict.requests) == 1


def test_gives_up_after_max_retries() -> None:
    h = Harness([connect_error(), connect_error(), connect_error()], max_retries=2)
    with pytest.raises(TransportError) as info:
        h.client.me.get()
    assert info.value.error_code == "transport_error"
    assert len(h.requests) == 3


def test_timeout_is_a_transport_error() -> None:
    h = Harness([httpx.ReadTimeout("slow")], max_retries=0)
    with pytest.raises(TransportError, match="timed out"):
        h.client.me.get()


# ——— per-call options (SDK_SPEC §1.1) ———


def test_options_apply_to_this_call_only() -> None:
    h = Harness([connect_error(), ok(), ok()], max_retries=2)
    with pytest.raises(TransportError):
        h.client.contacts.upsert(
            {"phone_number": "0812"}, "wc-1", options={"max_retries": 0, "headers": {"X-Trace": "t1"}, "timeout": 3}
        )
    assert len(h.requests) == 1
    assert h.requests[0].headers["Idempotency-Key"] == "wc-1"
    assert h.requests[0].headers["X-Trace"] == "t1"
    assert h.requests[0].extensions["timeout"]["read"] == 3
    h.client.me.get()
    assert "X-Trace" not in h.requests[1].headers
    assert h.requests[1].extensions["timeout"]["read"] == 15.0


def test_headers_owned_by_the_sdk_cannot_be_overridden() -> None:
    h = Harness([ok()])
    with pytest.raises(ValueError, match="set by the SDK"):
        h.client.me.get(options={"headers": {"plasgos-signature": "v1=forged"}})
    with pytest.raises(ValueError):
        h.client.messages.send({"endpoint_id": "e", "contact": {"id": "c"}, "content": Content.text("x")}, options={"headers": {"Idempotency-Key": "k"}})
    assert h.requests == []


def test_next_pages_keep_the_options() -> None:
    page = lambda n, nxt: ok([{"id": n}], {"pagination": {"page": n, "has_next_page": nxt}})  # noqa: E731
    h = Harness([page(1, True), page(2, False)])
    items = list(h.client.contacts.list(per_page=1, options={"headers": {"X-Trace": "t2"}}).auto_paging())
    assert items == [{"id": 1}, {"id": 2}]
    assert [r.headers.get("X-Trace") for r in h.requests] == ["t2", "t2"]
    assert str(h.requests[1].url).endswith("/api/v3/contacts?per_page=1&page=2")


# ——— logging (SDK_SPEC §1.2) ———


def test_debug_log_per_attempt_without_secrets(caplog: pytest.LogCaptureFixture) -> None:
    h = Harness([fail(503, "service_unavailable", "service_unavailable"), ok({"id": "c1"})])
    with caplog.at_level(logging.DEBUG, logger="plasgos_crm"):
        h.client.contacts.list(search="0812345")
    events = [r.plasgos_crm for r in caplog.records]  # type: ignore[attr-defined]
    assert [(e["attempt"], e["status"], e["error_code"]) for e in events] == [(1, 503, "service_unavailable"), (2, 200, None)]
    assert events[0]["path"] == "/api/v3/contacts" and events[0]["retry_in_ms"] is not None
    assert events[1]["retry_in_ms"] is None
    text = caplog.text + repr(events)
    for secret in ("ak_test", "sk_test", "0812345", "v1="):
        assert secret not in text


def test_transport_error_logged_without_status(caplog: pytest.LogCaptureFixture) -> None:
    h = Harness([connect_error()], max_retries=0)
    with caplog.at_level(logging.DEBUG, logger="plasgos_crm"), pytest.raises(TransportError):
        h.client.me.get()
    assert caplog.records[0].plasgos_crm["status"] is None  # type: ignore[attr-defined]
    assert caplog.records[0].plasgos_crm["error_code"] == "transport_error"  # type: ignore[attr-defined]


# ——— errors (SDK_SPEC §5) ———


def test_maps_v3_envelope_onto_every_field() -> None:
    h = Harness([
        {
            "status": 422,
            "body": {
                "success": False,
                "message": "Isi permintaan tidak sesuai format",
                "error": {
                    "code": "validation_failed",
                    "type": "invalid_request",
                    "details": {"a": 1},
                    "fields": [{"field": "contact.phone_number", "code": "required", "message": "Wajib diisi"}],
                    "doc_url": "https://docs/x",
                },
                "meta": {"request_id": "req_9"},
            },
        }
    ])
    with pytest.raises(ValidationError) as info:
        h.client.contacts.create({})
    e = info.value
    assert (e.message, e.http_status, e.error_code, e.error_type) == (
        "Isi permintaan tidak sesuai format", 422, "validation_failed", "invalid_request")
    assert (e.details, e.doc_url, e.request_id) == ({"a": 1}, "https://docs/x", "req_9")
    assert e.fields == [{"field": "contact.phone_number", "code": "required", "message": "Wajib diisi"}]
    assert e.errors == [{"message": "Wajib diisi", "path": ["contact", "phone_number"]}]


@pytest.mark.parametrize(
    "status,code,kind",
    [
        (401, "api_key_invalid", AuthenticationError),
        (401, "signature_expired", SignatureExpiredError),
        (403, "not_entitled", PlanError),
        (403, "scope_missing", PermissionError),
        (404, "contact_not_found", NotFoundError),
        (409, "identity_taken", ConflictError),
        (400, "malformed_json", BadRequestError),
        (413, "payload_too_large", ValidationError),
        (429, "daily_quota_exceeded", RateLimitError),
        (502, "graph_error", ChannelError),
        (503, "service_unavailable", ServiceUnavailableError),
    ],
)
def test_error_classes(status: int, code: str, kind: "type[PlasgosApiError]") -> None:
    h = Harness([fail(status, code)], max_retries=0)
    with pytest.raises(kind) as info:
        h.client.contacts.create({})
    assert info.value.error_code == code


def test_error_hierarchy() -> None:
    assert issubclass(SignatureExpiredError, AuthenticationError)
    assert issubclass(PlanError, PermissionError)
    assert issubclass(ChannelError, ServerError)
    assert issubclass(ServiceUnavailableError, ServerError)


def test_non_envelope_responses() -> None:
    h = Harness([{"status": 200, "body": "<html>"}])
    with pytest.raises(PlasgosApiError) as info:
        h.client.me.get()
    assert info.value.error_code == "unexpected_response"
    h = Harness([{"status": 500, "body": "<html>"}], max_retries=0)
    with pytest.raises(ServerError) as server_info:
        h.client.me.get()
    assert server_info.value.error_code == "unexpected_response"
    h = Harness([{"status": 200}])
    assert h.client.me.get().data is None


def test_network_failure_is_transport_error() -> None:
    h = Harness([connect_error()], max_retries=0)
    with pytest.raises(TransportError) as info:
        h.client.me.get()
    assert isinstance(info.value, PlasgosApiError)


# ——— responses (SDK_SPEC §4) ———


def test_response_fields() -> None:
    h = Harness([
        {
            "status": 201,
            "body": {"success": True, "message": "Kontak dibuat", "data": {"id": "c1"}, "meta": {"request_id": "req_7", "created": True}},
            "headers": {"RateLimit-Limit": "120", "RateLimit-Remaining": "119", "RateLimit-Reset": "30", "Idempotent-Replayed": "true", "X-Request-Id": "req_hdr"},
        }
    ])
    r = h.client.contacts.upsert({"phone_number": "0812"})
    assert (r.http_status, r.message, r.data) == (201, "Kontak dibuat", {"id": "c1"})
    assert r.meta["created"] is True
    assert r.request_id == "req_7"
    assert (r.rate_limit.limit, r.rate_limit.remaining, r.rate_limit.reset) == (120, 119, 30)
    assert r.replayed is True
    assert r.header("X-Request-Id") == "req_hdr"


def test_auto_paging_walks_every_page_with_same_query() -> None:
    def page(n: int, items: list[Any], has_next: bool) -> dict[str, Any]:
        return ok(items, {"request_id": "r", "pagination": {"page": n, "per_page": 2, "total": 3, "total_pages": 2, "has_next_page": has_next, "has_prev_page": n > 1}})

    h = Harness([page(1, [{"id": 1}, {"id": 2}], True), page(2, [{"id": 3}], False)])
    first = h.client.contacts.list(tag="vip", per_page=2)
    assert len(first) == 2
    assert [x["id"] for x in first] == [1, 2]
    assert first.pagination["total"] == 3
    assert list(first.auto_paging()) == [{"id": 1}, {"id": 2}, {"id": 3}]
    assert str(h.requests[1].url) == "https://crm.example/api/v3/contacts?tag=vip&per_page=2&page=2"


def test_resource_routes() -> None:
    c = Harness([ok() for _ in range(9)])
    c.client.messages.send_options(phone_number="0812")
    c.client.followups.unenroll("f1", "c/1")
    c.client.followups.enroll("f1", {"contact": {"id": "c1"}}, "enrol-1")
    c.client.webhooks.rotate_secret("w1")
    c.client.webhooks.update("w1", {"enabled": False})
    c.client.webhooks.delete("w1")
    c.client.endpoints.templates("e 1", per_page=5)
    c.client.contact_groups.list()
    c.client.template_variables.list()
    assert [(r.method, r.url.raw_path.decode()) for r in c.requests] == [
        ("GET", "/api/v3/messages/send-options?phone_number=0812"),
        ("DELETE", "/api/v3/followups/f1/enrollments/c%2F1"),
        ("POST", "/api/v3/followups/f1/enrollments"),
        ("POST", "/api/v3/webhooks/w1/rotate-secret"),
        ("PATCH", "/api/v3/webhooks/w1"),
        ("DELETE", "/api/v3/webhooks/w1"),
        ("GET", "/api/v3/endpoints/e%201/templates?per_page=5"),
        ("GET", "/api/v3/contact-groups"),
        ("GET", "/api/v3/template-variables"),
    ]
    assert c.requests[2].headers["Idempotency-Key"] == "enrol-1"
    assert "Idempotency-Key" not in c.requests[4].headers


# ——— Content builders (SDK_SPEC §3.4) ———


def test_content_builders() -> None:
    assert Content.text("Halo") == {"type": "text", "text": {"body": "Halo", "preview_url": False}}
    assert Content.document("https://x/inv.pdf", "inv.pdf") == {
        "type": "document",
        "media": {"url": "https://x/inv.pdf", "caption": None, "file_name": "inv.pdf", "mime_type": None},
    }
    with pytest.raises(ValueError):
        Content.media("template", "https://x")
    template = (
        Content.template("tpl").dynamic("name", "{{full_name}}").custom("order", "INV-1").custom("name", "Budi")
        .location(-6.2, 106.8, "Toko")
    )
    assert template.to_dict() == {
        "type": "template",
        "template": {
            "template_id": "tpl",
            "variables": [{"param": "name", "type": "custom", "value": "Budi"}, {"param": "order", "type": "custom", "value": "INV-1"}],
            "location": {"latitude": -6.2, "longitude": 106.8, "name": "Toko", "address": None},
        },
    }


def test_template_content_serialised_inside_body() -> None:
    h = Harness([ok()])
    h.client.messages.send({"endpoint_id": "e1", "content": Content.template("tpl").custom("a", "1")})
    assert json.loads(h.requests[0].content)["content"]["template"]["variables"] == [{"param": "a", "type": "custom", "value": "1"}]


def test_rejects_missing_credentials() -> None:
    with pytest.raises(ValueError):
        Client("", "s")
    with pytest.raises(ValueError):
        Client("k", "")
