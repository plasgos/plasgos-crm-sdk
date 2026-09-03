from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

__all__ = ["Envelope"]


@dataclass(frozen=True)
class Envelope:
    """Normalised response wrapper returned by every successful SDK call."""

    status: int | bool
    message: str | None
    data: Any
    metadata: dict[str, Any] = field(default_factory=dict)
    raw: Any = None
    http_status: int = 0
