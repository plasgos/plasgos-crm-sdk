from __future__ import annotations

from .._envelope import Envelope
from ._base import Resource

__all__ = ["ReferencesResource"]


class ReferencesResource(Resource):
    def variables(self) -> Envelope:
        """List available WABA template variables."""
        return self._http.request("GET", "/api/v1/references/variables")

    def contacts(
        self,
        *,
        page: int | None = None,
        limit: int | None = None,
        search: str | None = None,
    ) -> Envelope:
        return self._http.request(
            "GET",
            "/api/v1/references/contacts",
            query={"page": page, "limit": limit, "search": search},
        )
