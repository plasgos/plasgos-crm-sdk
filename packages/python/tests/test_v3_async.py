"""AsyncClient: same contract as Client (docs/SDK_SPEC.md §1, §3–§6), driven with asyncio.run()."""

from __future__ import annotations

import asyncio
import inspect
import json
from typing import Any, Union

import httpx
import pytest

from plasgos_crm import AsyncClient, AsyncPage, Client, ConflictError, NotFoundError, TransportError

Reply = Union[dict[str, Any], Exception]


class AsyncHarness:
    """httpx.MockTransport (async) double: answers from a queue and records every request."""

    def __init__(self, responses: list[Reply], **options: Any) -> None:
        self.responses = list(responses)
        self.requests: list[httpx.Request] = []
        self.sleeps: list[float] = []
        self.now = 1_700_000_000

        async def sleeper(seconds: float) -> None:
            self.sleeps.append(seconds)
            self.now += 1

        self.client = AsyncClient(
            "ak_test",
            "sk_test",
            base_url="https://crm.example",
            transport=httpx.MockTransport(self._handle),
            sleeper=sleeper,
            clock=lambda: self.now,
            **options,
        )

    async def _handle(self, request: httpx.Request) -> httpx.Response:
        await request.aread()
        self.requests.append(request)
        reply = self.responses.pop(0)
        if isinstance(reply, Exception):
            raise reply
        return httpx.Response(reply["status"], content=json.dumps(reply["body"]).encode(), headers=reply.get("headers", {}))


def ok(data: Any = None, meta: dict[str, Any] | None = None, status: int = 200) -> dict[str, Any]:
    return {"status": status, "body": {"success": True, "message": "OK", "data": data, "meta": {"request_id": "req_ok", **(meta or {})}}}


def fail(status: int, code: str) -> dict[str, Any]:
    error: dict[str, Any] = {"code": code, "type": "x", "details": None, "fields": [], "doc_url": None}
    return {"status": status, "body": {"success": False, "message": "Galat", "error": error, "meta": {"request_id": "req_err"}}}


RESOURCES = [
    "me", "channels", "endpoints", "template_variables", "messages", "contacts",
    "contact_groups", "pipelines", "leads", "broadcasts", "followups", "webhooks",
]


@pytest.mark.parametrize("name", RESOURCES)
def test_same_methods_and_parameters_as_the_sync_client(name: str) -> None:
    sync_resource = getattr(Client("a", "s"), name)
    async_resource = getattr(AsyncClient("a", "s"), name)
    sync_methods = {m for m in dir(sync_resource) if not m.startswith("_")}
    assert sync_methods == {m for m in dir(async_resource) if not m.startswith("_")}
    for method in sync_methods:
        expected = inspect.signature(getattr(sync_resource, method)).parameters
        actual = inspect.signature(getattr(async_resource, method)).parameters
        assert [(p.name, p.kind, p.default) for p in expected.values()] == [(p.name, p.kind, p.default) for p in actual.values()], method
        assert inspect.iscoroutinefunction(getattr(async_resource, method)) or inspect.isasyncgenfunction(getattr(async_resource, method)), method


def test_signed_post_key_reused_across_retries() -> None:
    async def run() -> AsyncHarness:
        h = AsyncHarness([httpx.ConnectError("down"), fail(503, "service_unavailable"), ok({"id": "c1"}, status=201)])
        res = await h.client.contacts.upsert({"phone_number": "0812"})
        assert res.http_status == 201 and dict(res.data) == {"id": "c1"}
        return h

    h = asyncio.run(run())
    assert len(h.requests) == 3 and len(h.sleeps) == 2
    assert len({r.headers["Idempotency-Key"] for r in h.requests}) == 1
    assert len({r.headers["Plasgos-Timestamp"] for r in h.requests}) == 3
    assert all(r.headers["Plasgos-Signature"].startswith("v1=") for r in h.requests)
    assert h.requests[0].content == b'{"phone_number":"0812"}'


def test_errors_are_typed_and_writes_not_retried_on_conflict() -> None:
    async def run() -> None:
        h = AsyncHarness([fail(409, "identity_taken")])
        with pytest.raises(ConflictError):
            await h.client.contacts.create({"phone_number": "0812"})
        assert len(h.requests) == 1
        h = AsyncHarness([fail(404, "contact_not_found")])
        with pytest.raises(NotFoundError) as caught:
            await h.client.contacts.get("c/1")
        assert caught.value.error_code == "contact_not_found"
        assert h.requests[0].url.raw_path == b"/api/v3/contacts/c%2F1"
        h = AsyncHarness([httpx.ReadTimeout("slow")], max_retries=0)
        with pytest.raises(TransportError, match="timed out"):
            await h.client.me.get()

    asyncio.run(run())


def test_auto_paging_and_per_call_options() -> None:
    def page(n: int, nxt: bool) -> dict[str, Any]:
        return ok([{"id": n}], {"pagination": {"page": n, "has_next_page": nxt}})

    async def run() -> tuple[list[Any], AsyncHarness]:
        h = AsyncHarness([page(1, True), page(2, False)])
        first = await h.client.contacts.list(tag="vip", options={"headers": {"X-Trace": "t"}, "timeout": 4})
        assert isinstance(first, AsyncPage) and len(first) == 1 and [dict(c) for c in first] == [{"id": 1}]
        return [dict(item) async for item in first.auto_paging()], h

    items, h = asyncio.run(run())
    assert items == [{"id": 1}, {"id": 2}]
    assert str(h.requests[1].url) == "https://crm.example/api/v3/contacts?tag=vip&page=2"
    assert [r.headers["X-Trace"] for r in h.requests] == ["t", "t"]
    assert h.requests[1].extensions["timeout"]["read"] == 4


def test_context_manager_closes_its_own_http_client() -> None:
    async def run() -> AsyncClient:
        async with AsyncClient("a", "s") as client:
            assert client.request is not None
        return client

    client = asyncio.run(run())
    assert client._http._client.is_closed  # noqa: SLF001


def test_rejects_missing_credentials() -> None:
    with pytest.raises(ValueError):
        AsyncClient("", "s")
