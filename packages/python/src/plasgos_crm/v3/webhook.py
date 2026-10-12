"""API v3 webhook verification (docs/SDK_SPEC.md §7).

::

    Plasgos-Webhook-Signature = "v1=" + hex(hmac_sha256(signing_secret, id + "." + timestamp + "." + raw_body))

During the 24 h after a secret rotation the header carries two ``v1=`` values; pass both secrets.
Deliveries may repeat — drop event ids you have already processed.
"""

from __future__ import annotations

import hashlib
import hmac
import json
import re
import time
from typing import Any, Iterable, Optional, Sequence, Union

from ..errors import WebhookVerificationError
from .signature import SIGNATURE_PREFIX

__all__ = ["Webhook", "WebhookEventType", "DEFAULT_TOLERANCE"]

DEFAULT_TOLERANCE = 300

_HEX = re.compile(r"^[0-9a-fA-F]+$")


class WebhookEventType:
    MESSAGE_RECEIVED = "message.received"
    MESSAGE_STATUS_UPDATED = "message.status_updated"
    CONTACT_CREATED = "contact.created"
    CONTACT_UPDATED = "contact.updated"
    LEAD_CREATED = "lead.created"
    LEAD_STAGE_CHANGED = "lead.stage_changed"
    DEAL_WON = "deal.won"
    DEAL_LOST = "deal.lost"
    BROADCAST_COMPLETED = "broadcast.completed"
    FOLLOWUP_ENROLLMENT_COMPLETED = "followup.enrollment.completed"
    FOLLOWUP_ENROLLMENT_STOPPED = "followup.enrollment.stopped"
    WEBHOOK_TEST = "webhook.test"




def _normalize(headers: Any) -> dict[str, str]:
    """Any casing, ``_`` or ``-``, and ``HTTP_*`` server keys (WSGI ``environ`` / Django ``META``)."""
    items: Iterable[tuple[Any, Any]] = headers.items() if hasattr(headers, "items") else headers
    out: dict[str, str] = {}
    for name, value in items:
        key = str(name).lower().replace("_", "-")
        if key.startswith("http-"):
            key = key[5:]
        if isinstance(value, bytes):
            value = value.decode("latin-1")
        elif isinstance(value, (list, tuple)):
            value = value[0] if value else ""
        out[key] = ("" if value is None else str(value)).strip()
    return out


def _hmac(secret: str, payload: str) -> bytes:
    return hmac.new(secret.encode("utf-8"), payload.encode("utf-8"), hashlib.sha256).digest()


def _fail(reason: str) -> WebhookVerificationError:
    return WebhookVerificationError(f"Webhook verification failed: {reason}")


class Webhook:
    DEFAULT_TOLERANCE = DEFAULT_TOLERANCE

    @staticmethod
    def verify(
        raw_body: Union[str, bytes],
        headers: Any,
        secrets: Union[str, Sequence[str]],
        *,
        tolerance: int = DEFAULT_TOLERANCE,
        now: Optional[int] = None,
    ) -> dict[str, Any]:
        """Verify a delivery and return the decoded event.

        :param raw_body: the request body exactly as received (not re-serialised JSON).
        :param headers: any mapping of headers (``request.headers``, WSGI ``environ``, Django ``META``).
        :param secrets: the current secret, or ``[current, previous]`` during rotation.
        """
        body = raw_body.decode("utf-8") if isinstance(raw_body, bytes) else raw_body
        h = _normalize(headers)
        event_id = h.get("plasgos-webhook-id", "")
        timestamp = h.get("plasgos-webhook-timestamp", "")
        signature = h.get("plasgos-webhook-signature", "")

        if not event_id or not timestamp or not signature:
            raise _fail("signature headers missing")
        if not timestamp.isdigit():
            raise _fail("invalid timestamp")
        current = int(time.time()) if now is None else now
        if abs(current - int(timestamp)) > tolerance:
            raise _fail("timestamp outside tolerance")

        candidates = [
            part[len(SIGNATURE_PREFIX):]
            for part in re.split(r"[\s,]+", signature)
            if part.startswith(SIGNATURE_PREFIX)
        ]
        if not candidates:
            raise _fail("unsupported signature version")

        payload = f"{event_id}.{timestamp}.{body}"
        matched = False
        for secret in [secrets] if isinstance(secrets, str) else list(secrets):
            if not isinstance(secret, str) or secret == "":
                continue
            expected = _hmac(secret, payload)
            for candidate in candidates:
                given = bytes.fromhex(candidate) if _HEX.match(candidate) and len(candidate) % 2 == 0 else b""
                if hmac.compare_digest(given, expected):
                    matched = True
        if not matched:
            raise _fail("signature mismatch")

        try:
            event = json.loads(body)
        except ValueError:
            raise _fail("body is not valid JSON") from None
        if not isinstance(event, dict) or "id" not in event or "type" not in event:
            raise _fail("body is not an event")
        if str(event["id"]) != event_id:
            raise _fail("event id does not match header")
        return event

    @staticmethod
    def signature_headers(event_id: str, timestamp: int, raw_body: str, secret: str) -> dict[str, str]:
        """Headers the CRM would send — for tests and local tooling."""
        signature = _hmac(secret, f"{event_id}.{timestamp}.{raw_body}").hex()
        return {
            "Plasgos-Webhook-Id": event_id,
            "Plasgos-Webhook-Timestamp": str(timestamp),
            "Plasgos-Webhook-Signature": SIGNATURE_PREFIX + signature,
        }
