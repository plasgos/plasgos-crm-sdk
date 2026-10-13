"""Optional smoke test against a real API v3 server. Skipped unless these are set:

    PLASGOS_LIVE_BASE_URL  e.g. https://api-crm.sandbox.plasgos.co.id or http://127.0.0.1:3001
    PLASGOS_LIVE_API_KEY, PLASGOS_LIVE_SECRET_KEY

Read paths and validation only — nothing here can deliver a message to a real customer.
"""

from __future__ import annotations

import os

import pytest

from plasgos_crm import (
    AuthenticationError,
    Client,
    Content,
    NotFoundError,
    PlasgosApiError,
    ValidationError,
)

ENV = os.environ
LIVE = all(ENV.get(k) for k in ("PLASGOS_LIVE_BASE_URL", "PLASGOS_LIVE_API_KEY", "PLASGOS_LIVE_SECRET_KEY"))
pytestmark = pytest.mark.skipif(not LIVE, reason="PLASGOS_LIVE_* not set")


def make(secret: str | None = None) -> Client:
    return Client(
        ENV["PLASGOS_LIVE_API_KEY"],
        secret or ENV["PLASGOS_LIVE_SECRET_KEY"],
        base_url=ENV["PLASGOS_LIVE_BASE_URL"],
        max_retries=0,
    )


def code_of(call: object) -> str | None:
    try:
        call()  # type: ignore[operator]
    except PlasgosApiError as error:
        return error.error_code
    return None


def test_me_and_query_with_reserved_and_unicode_chars() -> None:
    with make() as client:
        me = client.me.get()
        assert me.http_status == 200
        assert "account:read" in me.data["scopes"]
        assert (me.request_id or "").startswith("req_")
        assert client.request("GET", "/me", {"search": "Budi & Sari/+62 👋", "page": 2}).http_status == 200


def test_wrong_secret_is_rejected() -> None:
    with make("plg_wrong") as client, pytest.raises(AuthenticationError) as info:
        client.me.get()
    assert info.value.error_code == "signature_invalid"


def test_channels_endpoints_and_message_read_paths() -> None:
    with make() as client:
        assert len(client.channels.list().data) > 0
        for endpoint in client.endpoints.list(per_page=5):
            assert endpoint["status"] in ("ready", "not_ready", "disconnected")
        assert client.messages.send_options(phone_number="6280000000001").data["contact"] is None
        assert code_of(lambda: client.messages.get("wamid.sdk-live-test-missing")) == "message_not_found"
        with pytest.raises(ValidationError) as info:
            client.messages.send({
                "endpoint_id": "x",
                "contact": {"id": "a", "phone_number": "081234567890"},
                "content": Content.text("uji"),
            })
        assert info.value.fields[0]["field"] == "contact.id"


def test_contacts_leads_broadcasts_followups_webhooks_read_paths() -> None:
    with make() as client:
        scopes = client.me.get().data["scopes"]
        if "contacts:read" in scopes:
            with pytest.raises(NotFoundError):
                client.contacts.lookup(email="sdk-live-test-missing@contoh.invalid")
            assert "total" in client.contacts.list(per_page=1).pagination
        if "leads:read" in scopes:
            for pipeline in client.pipelines.list().data:
                for stage in pipeline["stages"]:
                    assert stage["kind"] in ("open", "won", "lost")
            assert code_of(lambda: client.leads.get("66f1dddddddddddddddddd99")) == "lead_not_found"
        if "broadcasts:read" in scopes:
            assert code_of(lambda: client.broadcasts.get("66f1eeeeeeeeeeeeeeeeee99")) == "broadcast_not_found"
        if "followups:read" in scopes:
            assert code_of(lambda: client.followups.get("66f1ffffffffffffffffff99")) == "followup_not_found"
        if "webhooks:manage" in scopes:
            assert code_of(lambda: client.webhooks.test("66f1abcdabcdabcdabcdab99")) == "webhook_not_found"
