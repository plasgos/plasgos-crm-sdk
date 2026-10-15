"""Webchat identity token (docs/SDK_SPEC.md §8): tells the website chat widget who the signed-in customer is.

Sign it on YOUR server with the widget's identity secret (``wcs_…``, shown once when the widget is created or the
secret is rotated) and hand only the token to the page::

    token = Webchat.sign_identity(os.environ["PLASGOS_WEBCHAT_IDENTITY_SECRET"], {"sub": str(user.id), "email": user.email})
    # <script>Plasgos("boot", { widgetId: "wgt_…", identityToken: {{ token|tojson }} });</script>

The secret must never reach the browser. JWT HS256 with claims ``sub``, ``email?``, ``name?``, ``phone?``, ``wid?``,
``iat``, ``exp``, ``jti``. Each token is single-use per visitor, so sign a fresh one per page render / sign-in.
"""

from __future__ import annotations

import base64
import hashlib
import hmac
import json
import time
import uuid
from typing import Any, Mapping, Optional

__all__ = ["Webchat", "IDENTITY_DEFAULT_TTL", "IDENTITY_MAX_TTL"]

IDENTITY_DEFAULT_TTL = 3600
IDENTITY_MAX_TTL = 86400


def _base64url(value: bytes) -> str:
    return base64.urlsafe_b64encode(value).rstrip(b"=").decode("ascii")


def _json(value: Any) -> bytes:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":")).encode("utf-8")


def _length(value: str) -> int:
    """Length in UTF-16 code units — the way the server (JavaScript) counts."""
    return len(value.encode("utf-16-le", "surrogatepass")) // 2


class Webchat:
    IDENTITY_DEFAULT_TTL = IDENTITY_DEFAULT_TTL
    IDENTITY_MAX_TTL = IDENTITY_MAX_TTL

    @staticmethod
    def sign_identity(
        secret: str,
        identity: Mapping[str, Any],
        *,
        widget_id: Optional[str] = None,
        ttl: int = IDENTITY_DEFAULT_TTL,
        now: Optional[int] = None,
        jti: Optional[str] = None,
    ) -> str:
        """Signed identity token (JWT HS256) for ``Plasgos("boot", { identityToken })``.

        :param identity: ``sub`` (str | int, required) = stable id of the customer in your system (≤ 255
            characters); ``email``, ``name``, ``phone`` (str | None) fill empty fields of the CRM contact
            (``None``/``""`` are left out).
        :param widget_id: binds the token to one widget (``wgt_…``).
        :param ttl: lifetime in seconds, 1–86400 (the server rejects longer).
        :param now: Unix seconds; for tests.
        :param jti: unique token id (≤ 128 characters); a random UUID by default.
        :raises ValueError: on invalid input.
        """
        if not isinstance(secret, str) or secret == "":
            raise ValueError("Webchat.sign_identity: secret is required")
        sub = identity.get("sub")
        if isinstance(sub, int) and not isinstance(sub, bool):
            sub = str(sub)
        if not isinstance(sub, str) or sub.strip() == "" or _length(sub) > 255:
            raise ValueError("Webchat.sign_identity: sub must be a non-empty string of at most 255 characters")
        if not isinstance(ttl, int) or isinstance(ttl, bool) or ttl < 1 or ttl > IDENTITY_MAX_TTL:
            raise ValueError(
                f"Webchat.sign_identity: ttl must be an integer between 1 and {IDENTITY_MAX_TTL} seconds"
            )
        if widget_id is not None and (not isinstance(widget_id, str) or widget_id == ""):
            raise ValueError("Webchat.sign_identity: widget_id must be a non-empty string")
        token_id = jti if jti is not None else str(uuid.uuid4())
        if not isinstance(token_id, str) or token_id == "" or _length(token_id) > 128:
            raise ValueError("Webchat.sign_identity: jti must be a non-empty string of at most 128 characters")
        iat = int(time.time()) if now is None else now

        # Fixed key order: tokens are byte-identical across the Node, PHP and Python SDKs (docs/vectors-v3.json).
        payload: dict[str, Any] = {"sub": sub}
        for key in ("email", "name", "phone"):
            value = identity.get(key)
            if value is None or value == "":
                continue
            if not isinstance(value, str):
                raise ValueError(f"Webchat.sign_identity: {key} must be a string")
            payload[key] = value
        if widget_id is not None:
            payload["wid"] = widget_id
        payload["iat"] = iat
        payload["exp"] = iat + ttl
        payload["jti"] = token_id

        signing_input = f"{_base64url(_json({'alg': 'HS256', 'typ': 'JWT'}))}.{_base64url(_json(payload))}"
        signature = hmac.new(secret.encode("utf-8"), signing_input.encode("ascii"), hashlib.sha256).digest()
        return f"{signing_input}.{_base64url(signature)}"
