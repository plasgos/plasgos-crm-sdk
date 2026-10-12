from __future__ import annotations

import warnings

import httpx

from ._http import HttpClient, resolve_base_url
from ._signature import signature_headers
from ._version import __version__
from .resources.accounts import AccountsResource
from .resources.messages import MessagesResource
from .resources.official import OfficialNamespace
from .resources.references import ReferencesResource

__all__ = ["LegacyClient", "PlasgosCrmClient"]


class LegacyClient:
    """LEGACY signed API client for ``/api/v1/*``.

    .. deprecated:: 2.0
        Use :class:`plasgos_crm.Client` (API Integration v3). Removed in 3.0.
        Constructing it emits a :class:`DeprecationWarning`.

    ::

        from plasgos_crm import LegacyClient

        client = LegacyClient(api_key, secret_key, base_url="sandbox")
        env = client.messages.send(
            channel="whatsapp",
            account_id="665f1c2e9a1b2c3d4e5f6a7b",
            receiver={"phone_number": "628123456789", "name": "John"},
            content={"type": "text", "data": {"text": "Halo"}},
        )
        client.close()

    Also usable as a context manager (``with LegacyClient(...) as client:``).
    """

    def __init__(
        self,
        api_key: str,
        secret_key: str,
        *,
        base_url: str | None = None,
        timeout: float = 15.0,
        max_retries: int = 2,
        signature_ttl: int = 300,
        user_agent: str | None = None,
        transport: httpx.BaseTransport | None = None,
        http_client: httpx.Client | None = None,
    ) -> None:
        warnings.warn(
            "LegacyClient (/api/v1) is deprecated and removed in 3.0; use plasgos_crm.Client (API v3).",
            DeprecationWarning,
            stacklevel=2,
        )
        if not api_key:
            raise ValueError("LegacyClient: api_key is required")
        if not secret_key:
            raise ValueError("LegacyClient: secret_key is required")

        self._api_key = api_key
        self._secret_key = secret_key
        self.signature_ttl = signature_ttl

        self._http = HttpClient(
            base_url=resolve_base_url(base_url),
            user_agent=user_agent or f"plasgos-crm-sdk-python/{__version__}",
            max_retries=max_retries,
            timeout=timeout,
            transport=transport,
            client=http_client,
            auth_headers=lambda: signature_headers(self._api_key, self._secret_key),
        )

        self.messages = MessagesResource(self._http)
        self.accounts = AccountsResource(self._http)
        self.references = ReferencesResource(self._http)
        self.official = OfficialNamespace(self._http)

    def signature_headers(self, timestamp: int | None = None) -> dict[str, str]:
        """The three auth headers the SDK would send right now."""
        return signature_headers(self._api_key, self._secret_key, timestamp)

    def close(self) -> None:
        self._http.close()

    def __enter__(self) -> "LegacyClient":
        return self

    def __exit__(self, *_exc: object) -> None:
        self.close()


#: Old name of :class:`LegacyClient`. Deprecated; removed in 3.0.
PlasgosCrmClient = LegacyClient
