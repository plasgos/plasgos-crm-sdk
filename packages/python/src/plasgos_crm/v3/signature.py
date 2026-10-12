"""API v3 request signing (docs/SDK_SPEC.md §2).

::

    string_to_sign = timestamp + "\\n" + METHOD + "\\n" + request_target + "\\n" + hex(sha256(raw_body))
    signature      = "v1=" + hex(hmac_sha256(secret_key, string_to_sign))

``request_target`` is the path + query exactly as sent (e.g. ``/api/v3/contacts?page=2``).
"""

from __future__ import annotations

import hashlib
import hmac
import time

__all__ = ["SIGNATURE_PREFIX", "string_to_sign", "sign_v3", "signature_headers_v3"]

SIGNATURE_PREFIX = "v1="


def _bytes(value: str | bytes) -> bytes:
    return value if isinstance(value, bytes) else value.encode("utf-8")


def string_to_sign(timestamp: int, method: str, request_target: str, raw_body: str | bytes) -> str:
    digest = hashlib.sha256(_bytes(raw_body)).hexdigest()
    return f"{timestamp}\n{method.upper()}\n{request_target}\n{digest}"


def sign_v3(
    secret_key: str, timestamp: int, method: str, request_target: str, raw_body: str | bytes
) -> str:
    payload = string_to_sign(timestamp, method, request_target, raw_body).encode("utf-8")
    return SIGNATURE_PREFIX + hmac.new(secret_key.encode("utf-8"), payload, hashlib.sha256).hexdigest()


def signature_headers_v3(
    api_key: str,
    secret_key: str,
    method: str,
    request_target: str,
    raw_body: str | bytes = b"",
    timestamp: int | None = None,
) -> dict[str, str]:
    ts = int(time.time()) if timestamp is None else timestamp
    return {
        "Plasgos-Api-Key": api_key,
        "Plasgos-Timestamp": str(ts),
        "Plasgos-Signature": sign_v3(secret_key, ts, method, request_target, raw_body),
    }
