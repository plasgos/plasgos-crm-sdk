from __future__ import annotations

from urllib.parse import quote

from ..._envelope import Envelope
from .._base import Resource

__all__ = ["OfficialAccountsResource"]


class OfficialAccountsResource(Resource):
    def list(self) -> Envelope:
        return self._http.request("GET", "/api/v1/official/accounts")

    def phone_numbers(self, account_id: str) -> Envelope:
        return self._http.request(
            "GET",
            f"/api/v1/official/accounts/phone-numbers/{quote(account_id, safe='')}",
        )
