from __future__ import annotations

from typing import Any

from ..._envelope import Envelope
from .._base import Resource, compact

__all__ = ["OfficialMessagesResource"]


class OfficialMessagesResource(Resource):
    def send_template(
        self,
        *,
        phone_number_id: str,
        to: str,
        template: dict[str, Any],
    ) -> Envelope:
        """Send a WABA official template message.

        ``template`` uses snake_case wire keys:
        ``{"id", "name", "category", "parameters": {"variables": [...], "attachment": {...}}}``.
        """
        body = compact(
            {
                "phone_number_id": phone_number_id,
                "to": to,
                "template": compact(dict(template)),
            }
        )
        return self._http.request("POST", "/api/v1/official/messages", json=body)
