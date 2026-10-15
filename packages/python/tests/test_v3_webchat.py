from __future__ import annotations

import base64
import hashlib
import hmac
import json
import re
import time
from pathlib import Path
from typing import Any

import pytest

from plasgos_crm import Webchat

DOC = json.loads((Path(__file__).resolve().parents[3] / "docs" / "vectors-v3.json").read_text(encoding="utf-8"))


def _sign(vector: dict[str, Any]) -> str:
    options = vector["options"]
    return Webchat.sign_identity(
        vector["secret"],
        vector["identity"],
        widget_id=options.get("widget_id"),
        ttl=options.get("ttl", Webchat.IDENTITY_DEFAULT_TTL),
        now=options.get("now"),
        jti=options.get("jti"),
    )


def _decode(part: str) -> str:
    return base64.urlsafe_b64decode(part + "=" * (-len(part) % 4)).decode("utf-8")


@pytest.mark.parametrize("vector", DOC["webchat_identity"], ids=lambda v: v["name"])
def test_matches_golden_vector(vector: dict[str, Any]) -> None:
    token = _sign(vector)
    assert token == vector["token"]
    header, payload, _ = token.split(".")
    assert _decode(header) == vector["header_json"]
    assert _decode(payload) == vector["payload_json"]


@pytest.mark.parametrize("vector", DOC["webchat_identity_invalid"], ids=lambda v: v["name"])
def test_rejects_invalid_input(vector: dict[str, Any]) -> None:
    with pytest.raises(ValueError, match=r"Webchat\.sign_identity"):
        _sign(vector)


def test_defaults_one_hour_current_time_random_jti_and_valid_signature() -> None:
    before = int(time.time())
    token = Webchat.sign_identity("wcs_secret", {"sub": "7"})
    header, payload, signature = token.split(".")
    claims = json.loads(_decode(payload))
    assert claims["iat"] >= before
    assert claims["exp"] - claims["iat"] == Webchat.IDENTITY_DEFAULT_TTL
    assert re.match(r"^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$", claims["jti"])
    expected = hmac.new(b"wcs_secret", f"{header}.{payload}".encode(), hashlib.sha256).digest()
    assert signature == base64.urlsafe_b64encode(expected).rstrip(b"=").decode()
    assert Webchat.sign_identity("wcs_secret", {"sub": "7"}) != token


def test_counts_length_like_the_server() -> None:
    # 128 astral characters = 256 UTF-16 code units: too long for the server, so rejected here as well.
    with pytest.raises(ValueError):
        Webchat.sign_identity("wcs_secret", {"sub": "\U0001F600" * 128})
