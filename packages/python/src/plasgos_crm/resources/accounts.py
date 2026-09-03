from __future__ import annotations

from urllib.parse import quote

from .._envelope import Envelope
from ._base import Resource

__all__ = ["AccountsResource"]


class AccountsResource(Resource):
    def list(self) -> Envelope:
        return self._http.request("GET", "/api/v1/accounts")

    def get(self, account_id: str) -> Envelope:
        return self._http.request("GET", f"/api/v1/accounts/{quote(account_id, safe='')}")
