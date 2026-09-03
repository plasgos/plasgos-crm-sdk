from __future__ import annotations

from typing import Any

from .._http import HttpClient

__all__ = ["Resource", "compact"]


class Resource:
    def __init__(self, http: HttpClient) -> None:
        self._http = http


def compact(data: dict[str, Any]) -> dict[str, Any]:
    """Recursively drop ``None`` values so we never send ``"key": null`` noise."""
    out: dict[str, Any] = {}
    for key, value in data.items():
        if value is None:
            continue
        out[key] = compact(value) if isinstance(value, dict) else value
    return out
