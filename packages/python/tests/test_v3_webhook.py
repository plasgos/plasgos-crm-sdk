from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import pytest

from plasgos_crm import Webhook, WebhookEventType, WebhookVerificationError

VECTOR = json.loads((Path(__file__).resolve().parents[3] / "docs" / "vectors-v3.json").read_text())["webhook"][0]
HEADERS = {
    "Plasgos-Webhook-Id": VECTOR["id"],
    "Plasgos-Webhook-Timestamp": str(VECTOR["timestamp"]),
    "Plasgos-Webhook-Signature": VECTOR["signature"],
}
NOW = VECTOR["timestamp"]


def test_golden_vector_verifies_with_any_header_style() -> None:
    signed = Webhook.signature_headers(VECTOR["id"], NOW, VECTOR["body"], VECTOR["secret"])
    assert signed["Plasgos-Webhook-Signature"] == VECTOR["signature"]
    event = Webhook.verify(VECTOR["body"], HEADERS, VECTOR["secret"], now=NOW)
    assert event["type"] == WebhookEventType.MESSAGE_RECEIVED
    lower = {k.lower(): v for k, v in HEADERS.items()}
    assert Webhook.verify(VECTOR["body"].encode(), lower, [VECTOR["secret"]], now=NOW)["id"] == VECTOR["id"]
    environ = {
        "HTTP_PLASGOS_WEBHOOK_ID": VECTOR["id"],
        "HTTP_PLASGOS_WEBHOOK_TIMESTAMP": str(NOW),
        "HTTP_PLASGOS_WEBHOOK_SIGNATURE": VECTOR["signature"],
    }
    assert Webhook.verify(VECTOR["body"], environ, VECTOR["secret"], now=NOW)["id"] == VECTOR["id"]
    as_lists = {k: [v] for k, v in HEADERS.items()}
    assert Webhook.verify(VECTOR["body"], as_lists, VECTOR["secret"], now=NOW)["id"] == VECTOR["id"]


def test_previous_secret_accepted_during_rotation() -> None:
    fresh = Webhook.signature_headers(VECTOR["id"], NOW, VECTOR["body"], "whsec_new")["Plasgos-Webhook-Signature"]
    both = {**HEADERS, "Plasgos-Webhook-Signature": f"{fresh},{VECTOR['signature']}"}
    assert Webhook.verify(VECTOR["body"], both, "whsec_new", now=NOW)["id"] == VECTOR["id"]
    assert Webhook.verify(VECTOR["body"], both, VECTOR["secret"], now=NOW)["id"] == VECTOR["id"]
    assert Webhook.verify(VECTOR["body"], HEADERS, ["whsec_new", VECTOR["secret"]], now=NOW)["id"] == VECTOR["id"]


@pytest.mark.parametrize(
    "body,headers,now,message",
    [
        (VECTOR["body"], {"Plasgos-Webhook-Id": VECTOR["id"]}, NOW, "headers missing"),
        (VECTOR["body"], {**HEADERS, "Plasgos-Webhook-Timestamp": "abc"}, NOW, "invalid timestamp"),
        (VECTOR["body"], HEADERS, NOW + 301, "outside tolerance"),
        (VECTOR["body"], {**HEADERS, "Plasgos-Webhook-Signature": "v0=abc"}, NOW, "unsupported signature"),
        (VECTOR["body"].replace("msg_1", "msg_2"), HEADERS, NOW, "signature mismatch"),
        (VECTOR["body"], {**HEADERS, "Plasgos-Webhook-Signature": "v1=zz"}, NOW, "signature mismatch"),
    ],
    ids=["missing", "bad-timestamp", "too-old", "no-v1", "tampered", "non-hex"],
)
def test_rejections(body: str, headers: dict[str, Any], now: int, message: str) -> None:
    with pytest.raises(WebhookVerificationError, match=message):
        Webhook.verify(body, headers, VECTOR["secret"], now=now)


def test_rejects_mismatched_id_and_non_event_body() -> None:
    body = '{"id":"evt_other","type":"message.received"}'
    signed = Webhook.signature_headers(VECTOR["id"], NOW, body, VECTOR["secret"])
    with pytest.raises(WebhookVerificationError, match="does not match"):
        Webhook.verify(body, signed, VECTOR["secret"], now=NOW)
    not_event = Webhook.signature_headers(VECTOR["id"], NOW, "[]", VECTOR["secret"])
    with pytest.raises(WebhookVerificationError, match="not an event"):
        Webhook.verify("[]", not_event, VECTOR["secret"], now=NOW)
