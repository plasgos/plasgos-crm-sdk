from __future__ import annotations

import json

import pytest

from plasgos_crm import MESSAGE_RECEIVED, WebhookVerificationError, verify_webhook

PAYLOAD = json.dumps(
    {"event": "message.received", "user": {"id": 42}, "timestamp": 1775644335}
)


def test_accepts_matching_token() -> None:
    event = verify_webhook({"x-hub-token": "secret"}, PAYLOAD, expected_token="secret")
    assert event["event"] == MESSAGE_RECEIVED
    assert event["user"]["id"] == 42


def test_rejects_mismatched_token() -> None:
    with pytest.raises(WebhookVerificationError):
        verify_webhook({"X-Hub-Token": "wrong"}, PAYLOAD, expected_token="secret")


def test_rejects_missing_token_when_expected() -> None:
    with pytest.raises(WebhookVerificationError):
        verify_webhook({}, PAYLOAD, expected_token="secret")


def test_skips_token_check_when_none_expected() -> None:
    assert verify_webhook({}, PAYLOAD)["event"] == "message.received"


def test_rejects_invalid_json() -> None:
    with pytest.raises(WebhookVerificationError):
        verify_webhook({"x-hub-token": "t"}, "not json", expected_token="t")


def test_accepts_bytes_body() -> None:
    event = verify_webhook({"x-hub-token": "t"}, PAYLOAD.encode(), expected_token="t")
    assert event["timestamp"] == 1775644335
