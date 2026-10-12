from __future__ import annotations

import httpx

from ._http import HttpClient, resolve_base_url
from ._version import __version__ as _VERSION
from .resources.credentials import CredentialsResource

__all__ = ["PlasgosCrmCredentialsClient"]


class PlasgosCrmCredentialsClient:
    """Client for the credential-management endpoints (``/v2/integration/*``).

    These use the CRM **user session** auth, not signed API auth:
    ``Authorization: Bearer <access_token>`` + ``fingerprint: <fingerprint>``.
    Pass the exact token string your CRM session issued — the SDK does not
    encrypt or refresh it.

    ::

        creds = PlasgosCrmCredentialsClient(access_token, fingerprint)
        creds.credentials.request()
        current = creds.credentials.get()
    """

    def __init__(
        self,
        access_token: str,
        fingerprint: str,
        *,
        base_url: str | None = None,
        timeout: float = 15.0,
        max_retries: int = 2,
        user_agent: str | None = None,
        transport: httpx.BaseTransport | None = None,
        http_client: httpx.Client | None = None,
    ) -> None:
        if not access_token:
            raise ValueError("PlasgosCrmCredentialsClient: access_token is required")
        if not fingerprint:
            raise ValueError("PlasgosCrmCredentialsClient: fingerprint is required")

        self._access_token = access_token
        self._fingerprint = fingerprint

        self._http = HttpClient(
            base_url=resolve_base_url(base_url),
            user_agent=user_agent or f"plasgos-crm-sdk-python/{_VERSION}",
            max_retries=max_retries,
            timeout=timeout,
            transport=transport,
            client=http_client,
            auth_headers=lambda: {
                "Authorization": f"Bearer {self._access_token}",
                "fingerprint": self._fingerprint,
            },
        )
        self.credentials = CredentialsResource(self._http)

    def close(self) -> None:
        self._http.close()

    def __enter__(self) -> "PlasgosCrmCredentialsClient":
        return self

    def __exit__(self, *_exc: object) -> None:
        self.close()
