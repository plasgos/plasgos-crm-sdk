from __future__ import annotations

import hmac
import json
from typing import Any, Mapping

from ..errors import WebhookVerificationError

__all__ = ["verify_webhook"]


def _read_header(headers: Mapping[str, Any], name: str) -> str | None:
    # Case-insensitive lookup that also tolerates httpx.Headers / dict / list values.
    getter = getattr(headers, "get", None)
    if callable(getter):
        value = headers.get(name) or headers.get(name.lower())
        if value is not None:
            return value[0] if isinstance(value, (list, tuple)) else str(value)
    for key, value in dict(headers).items():
        if str(key).lower() == name.lower():
            return value[0] if isinstance(value, (list, tuple)) else str(value)
    return None


def verify_webhook(
    headers: Mapping[str, Any],
    raw_body: str | bytes,
    *,
    expected_token: str | None = None,
) -> dict[str, Any]:
    """Verify an inbound webhook request and return the parsed event dict.

    Plasgos CRM does not HMAC-sign webhook payloads; it sends your
    ``verify_token`` in the ``x-hub-token`` header. This compares that header
    with a constant-time compare and parses the JSON body.

    Raises :class:`plasgos_crm.errors.WebhookVerificationError` on mismatch or
    invalid JSON.
    """
    if expected_token is not None:
        received = _read_header(headers, "x-hub-token")
        if received is None or not hmac.compare_digest(received, expected_token):
            raise WebhookVerificationError(
                "Webhook verification failed: x-hub-token mismatch"
            )

    text = raw_body.decode("utf-8") if isinstance(raw_body, (bytes, bytearray)) else raw_body
    try:
        parsed = json.loads(text)
    except ValueError as err:
        raise WebhookVerificationError(
            "Webhook verification failed: body is not valid JSON"
        ) from err

    if not isinstance(parsed, dict):
        raise WebhookVerificationError(
            "Webhook verification failed: body is not an object"
        )
    return parsed
