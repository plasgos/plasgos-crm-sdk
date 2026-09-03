"""HMAC request signing, identical to the server."""

from __future__ import annotations

import hashlib
import hmac
import time

__all__ = ["compute_signature", "current_timestamp", "signature_headers"]


def compute_signature(api_key: str, secret_key: str, timestamp: int) -> str:
    """``lowercase_hex(HMAC_SHA256(secret_key, f"{api_key}:{timestamp}"))``.

    ``timestamp`` is Unix time in seconds. Method / path / body are not signed.
    """
    return hmac.new(
        secret_key.encode("utf-8"),
        f"{api_key}:{timestamp}".encode("utf-8"),
        hashlib.sha256,
    ).hexdigest().lower()


def current_timestamp() -> int:
    return int(time.time())


def signature_headers(
    api_key: str, secret_key: str, timestamp: int | None = None
) -> dict[str, str]:
    ts = current_timestamp() if timestamp is None else timestamp
    return {
        "x-api-key": api_key,
        "x-timestamp": str(ts),
        "x-signature": compute_signature(api_key, secret_key, ts),
    }
