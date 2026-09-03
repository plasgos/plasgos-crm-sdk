from __future__ import annotations

from typing import Any, Iterable
from urllib.parse import quote

from ..._envelope import Envelope
from .._base import Resource, compact

__all__ = ["OfficialBroadcastsResource"]


class OfficialBroadcastsResource(Resource):
    def create(
        self,
        *,
        account_id: str,
        template_id: str,
        phone_number_id: str,
        name: str,
        scheduled_at: str,
        request_id: str,
        recipients: Iterable[dict[str, Any]],
        description: str | None = None,
        variables: list[dict[str, str]] | None = None,
        location: dict[str, Any] | None = None,
    ) -> Envelope:
        """Create a WABA official broadcast (HTTP 201).

        Max 1000 recipients/day grouped by the ``scheduled_at`` date;
        ``request_id`` is the idempotency key. ``scheduled_at`` format:
        ``"YYYY-MM-DD HH:MM:SS"``.
        """
        body = compact(
            {
                "account_id": account_id,
                "template_id": template_id,
                "phone_number_id": phone_number_id,
                "name": name,
                "description": description,
                "scheduled_at": scheduled_at,
                "request_id": request_id,
                "variables": variables,
                "location": location,
                "recipients": [compact(dict(r)) for r in recipients],
            }
        )
        return self._http.request("POST", "/api/v1/official/broadcasts", json=body)

    def list(
        self,
        *,
        page: int | None = None,
        limit: int | None = None,
        status: str | None = None,
        search: str | None = None,
    ) -> Envelope:
        return self._http.request(
            "GET",
            "/api/v1/official/broadcasts",
            query={"page": page, "limit": limit, "status": status, "search": search},
        )

    def monitoring(self, broadcast_id: str) -> Envelope:
        return self._http.request(
            "GET",
            f"/api/v1/official/broadcasts/{quote(broadcast_id, safe='')}/monitoring",
        )
