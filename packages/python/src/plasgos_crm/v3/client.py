from __future__ import annotations

import platform
import time
from typing import Any, Callable, Optional

import httpx

from .._http import resolve_base_url
from .._version import __version__
from ._api_client import ApiClient, Query
from .resources import (
    BroadcastsResource,
    ChannelsResource,
    ContactGroupsResource,
    ContactsResource,
    EndpointsResource,
    FollowupsResource,
    LeadsResource,
    MeResource,
    MessagesResource,
    PipelinesResource,
    TemplateVariablesResource,
    WebhooksResource,
)
from .response import ApiResponse

__all__ = ["Client"]


class Client:
    """Plasgos CRM API Integration v3 client (``/api/v3/*``).

    ::

        from plasgos_crm import Client, Content

        with Client(api_key, secret_key, base_url="sandbox") as client:
            res = client.messages.send(
                {
                    "endpoint_id": "6281100000001",
                    "recipient": {"phone_number": "081234567890"},
                    "content": Content.text("Halo"),
                },
                idempotency_key="order-1001-shipped",
            )

    :param base_url: ``production`` (default), ``sandbox``, or an absolute URL (host only; ``/api/v3`` is appended).
    :param timeout: per-request timeout in seconds.
    :param max_retries: automatic retries (docs/SDK_SPEC.md §6).
    :param max_retry_wait: longest ``Retry-After`` in seconds honoured automatically.
    :param user_agent: sent as ``User-Agent``; integrations append themselves.
    :param transport: injectable ``httpx`` transport (e.g. ``httpx.MockTransport``).
    :param http_client: your own ``httpx.Client`` (not closed by :meth:`close`).
    :param sleeper: test hook, waits the given seconds between retries.
    :param clock: test hook, current Unix time in seconds (signing timestamp).
    """

    def __init__(
        self,
        api_key: str,
        secret_key: str,
        *,
        base_url: Optional[str] = None,
        timeout: float = 15.0,
        max_retries: int = 2,
        max_retry_wait: float = 30.0,
        user_agent: Optional[str] = None,
        transport: Optional[httpx.BaseTransport] = None,
        http_client: Optional[httpx.Client] = None,
        sleeper: Optional[Callable[[float], None]] = None,
        clock: Optional[Callable[[], int]] = None,
    ) -> None:
        if not api_key:
            raise ValueError("Client: api_key is required")
        if not secret_key:
            raise ValueError("Client: secret_key is required")

        self._http = ApiClient(
            base_url=resolve_base_url(base_url),
            api_key=api_key,
            secret_key=secret_key,
            user_agent=user_agent
            or f"plasgos-crm-sdk-python/{__version__} python/{platform.python_version()}",
            max_retries=max_retries,
            max_retry_wait=max_retry_wait,
            timeout=timeout,
            sleeper=sleeper or time.sleep,
            clock=clock or (lambda: int(time.time())),
            transport=transport,
            http_client=http_client,
        )

        self.me = MeResource(self._http)
        self.channels = ChannelsResource(self._http)
        self.endpoints = EndpointsResource(self._http)
        self.template_variables = TemplateVariablesResource(self._http)
        self.messages = MessagesResource(self._http)
        self.contacts = ContactsResource(self._http)
        self.contact_groups = ContactGroupsResource(self._http)
        self.pipelines = PipelinesResource(self._http)
        self.leads = LeadsResource(self._http)
        self.broadcasts = BroadcastsResource(self._http)
        self.followups = FollowupsResource(self._http)
        self.webhooks = WebhooksResource(self._http)

    def request(
        self,
        method: str,
        path: str,
        query: Optional[Query] = None,
        body: Any = None,
        idempotency_key: Optional[str] = None,
    ) -> ApiResponse:
        """Escape hatch for endpoints without a resource method yet."""
        return self._http.request(method, path, query, body, idempotency_key)

    def close(self) -> None:
        self._http.close()

    def __enter__(self) -> "Client":
        return self

    def __exit__(self, *_exc: object) -> None:
        self.close()
