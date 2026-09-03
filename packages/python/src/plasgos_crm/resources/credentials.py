from __future__ import annotations

from .._envelope import Envelope
from ._base import Resource, compact

__all__ = ["CredentialsResource"]


class CredentialsResource(Resource):
    """Credential-management endpoints (``/v2/integration/*``).

    Reached via :class:`plasgos_crm.PlasgosCrmCredentialsClient`.
    """

    def get(self) -> Envelope:
        """Current API credential for the logged-in user (``data`` is ``None`` if none)."""
        return self._http.request("GET", "/v2/integration")

    def request(self) -> Envelope:
        """Request API access — creates a pending credential awaiting admin approval."""
        return self._http.request("POST", "/v2/integration")

    def regenerate(self) -> Envelope:
        """Regenerate api_key + secret_key (only allowed when status is ``active``)."""
        return self._http.request("POST", "/v2/integration/regenerate")

    def messages(self, *, page: int | None = None, limit: int | None = None) -> Envelope:
        return self._http.request(
            "GET",
            "/v2/integration/messages",
            query={"page": page, "limit": limit},
        )

    def subscribe_webhook(
        self, *, webhook_url: str, verify_token: str | None = None
    ) -> Envelope:
        return self._http.request(
            "POST",
            "/v2/integration/webhook",
            json=compact({"webhook_url": webhook_url, "verify_token": verify_token}),
        )
