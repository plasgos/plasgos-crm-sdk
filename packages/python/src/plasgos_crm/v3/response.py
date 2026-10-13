from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Callable, Generic, Iterator, Optional, TypeVar

__all__ = ["ApiResponse", "Page", "RateLimit"]

T = TypeVar("T")


@dataclass(frozen=True)
class RateLimit:
    """``RateLimit-*`` headers of a v3 response (``None`` when absent)."""

    limit: Optional[int] = None
    remaining: Optional[int] = None
    reset: Optional[int] = None


@dataclass(frozen=True)
class ApiResponse(Generic[T]):
    """A single v3 result (docs/SDK_SPEC.md §4.1). ``data`` keeps the wire format (snake_case).

    ``ApiResponse[Contact]`` — ``data`` typed from OpenAPI (:mod:`plasgos_crm.v3.types`).
    """

    http_status: int
    message: str
    data: T
    meta: dict[str, Any] = field(default_factory=dict)
    request_id: Optional[str] = None
    rate_limit: RateLimit = field(default_factory=RateLimit)
    replayed: bool = False
    headers: dict[str, str] = field(default_factory=dict)

    def header(self, name: str) -> Optional[str]:
        return self.headers.get(name.lower())


class Page(Generic[T]):
    """A list result (docs/SDK_SPEC.md §4.2).

    ::

        for contact in client.contacts.list(per_page=100).auto_paging():
            ...
    """

    def __init__(self, response: ApiResponse[Any], fetch_page: Callable[[int], "Page[T]"]) -> None:
        self.response = response
        self._fetch_page = fetch_page
        self.items: list[T] = list(response.data) if isinstance(response.data, list) else []
        raw = response.meta.get("pagination") if isinstance(response.meta.get("pagination"), dict) else {}
        assert isinstance(raw, dict)
        self.pagination: dict[str, Any] = {
            "page": int(raw.get("page", 1)),
            "per_page": int(raw.get("per_page", len(self.items))),
            "total": int(raw.get("total", len(self.items))),
            "total_pages": int(raw.get("total_pages", 1)),
            "has_next_page": bool(raw.get("has_next_page", False)),
            "has_prev_page": bool(raw.get("has_prev_page", False)),
        }

    def has_next_page(self) -> bool:
        return bool(self.pagination["has_next_page"])

    def next_page(self) -> Optional["Page[T]"]:
        return self._fetch_page(int(self.pagination["page"]) + 1) if self.has_next_page() else None

    def auto_paging(self) -> Iterator[T]:
        """Every item of every page, fetching the next page lazily."""
        page: Optional[Page[T]] = self
        while page is not None:
            yield from page.items
            page = page.next_page()

    def __iter__(self) -> Iterator[T]:
        return iter(self.items)

    def __len__(self) -> int:
        return len(self.items)
