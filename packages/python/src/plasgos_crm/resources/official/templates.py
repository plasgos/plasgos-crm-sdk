from __future__ import annotations

from urllib.parse import quote

from ..._envelope import Envelope
from .._base import Resource

__all__ = ["OfficialTemplatesResource"]


class OfficialTemplatesResource(Resource):
    def list(self, account_id: str, *, category: str | None = None) -> Envelope:
        """List APPROVED templates. ``category``: MARKETING | UTILITY | AUTHENTICATION | OTP."""
        return self._http.request(
            "GET",
            f"/api/v1/official/templates/{quote(account_id, safe='')}",
            query={"category": category},
        )
