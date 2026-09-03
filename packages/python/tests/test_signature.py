from __future__ import annotations

import re

from plasgos_crm import compute_signature, signature_headers


def test_matches_golden_vectors(signature_vectors: list[dict]) -> None:
    for v in signature_vectors:
        assert compute_signature(v["api_key"], v["secret_key"], v["timestamp"]) == v["signature"]


def test_is_lowercase_hex() -> None:
    assert re.fullmatch(r"[0-9a-f]{64}", compute_signature("k", "s", 1))


def test_headers() -> None:
    assert signature_headers("test_key", "test_secret", 1700000000) == {
        "x-api-key": "test_key",
        "x-timestamp": "1700000000",
        "x-signature": "e18a0c152134399f8efa60f058ced975a2f24657e231c3bd95351d527a3c1fcb",
    }
