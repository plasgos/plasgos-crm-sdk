from __future__ import annotations

import json
from pathlib import Path

import pytest

VECTORS_PATH = Path(__file__).resolve().parents[3] / "docs" / "vectors.json"


@pytest.fixture(scope="session")
def signature_vectors() -> list[dict]:
    return json.loads(VECTORS_PATH.read_text())["vectors"]
