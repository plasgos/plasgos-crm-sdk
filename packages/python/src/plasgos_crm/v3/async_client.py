from __future__ import annotations

import asyncio
import platform
import time
from typing import Any, Awaitable, Callable, Optional

import httpx

from .._http import resolve_base_url
from .._version import __version__
from ._api_client import AsyncApiClient, Query, RequestOptions
from .async_resources import (
    AsyncBroadcastsResource,
    AsyncChannelsResource,
    AsyncContactGroupsResource,
    AsyncContactsResource,
    AsyncEndpointsResource,
    AsyncFollowupsResource,
    AsyncLeadsResource,
    AsyncMeResource,
    AsyncMessagesResource,
    AsyncPipelinesResource,
    AsyncTemplateVariablesResource,
    AsyncWebhooksResource,
)
from .response import ApiResponse

__all__ = ["AsyncClient"]


class AsyncClient:
    """Plasgos CRM API Integration v3 client for ``asyncio`` — same surface as :class:`Client`.

    ::

        from plasgos_crm import AsyncClient, Content

        async with AsyncClient(api_key, secret_key, base_url="sandbox") as client:
            res = await client.messages.send(
                {
                    "endpoint_id": "6281100000001",
                    "contact": {"phone_number": "081234567890"},
                    "content": Content.text("Halo"),
                },
                idempotency_key="order-1001-shipped",
            )
            page = await client.contacts.list(per_page=100)
            async for contact in page.auto_paging():
                ...

    Parameters as :class:`Client`, except ``transport`` (``httpx.AsyncBaseTransport``),
    ``http_client`` (``httpx.AsyncClient``, not closed by :meth:`aclose`) and ``sleeper`` (async).
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
        transport: Optional[httpx.AsyncBaseTransport] = None,
        http_client: Optional[httpx.AsyncClient] = None,
        sleeper: Optional[Callable[[float], Awaitable[None]]] = None,
        clock: Optional[Callable[[], int]] = None,
    ) -> None:
        if not api_key:
            raise ValueError("AsyncClient: api_key is required")
        if not secret_key:
            raise ValueError("AsyncClient: secret_key is required")

        self._http = AsyncApiClient(
            base_url=resolve_base_url(base_url),
            api_key=api_key,
            secret_key=secret_key,
            user_agent=user_agent
            or f"plasgos-crm-sdk-python/{__version__} python/{platform.python_version()} asyncio",
            max_retries=max_retries,
            max_retry_wait=max_retry_wait,
            timeout=timeout,
            sleeper=sleeper or asyncio.sleep,
            clock=clock or (lambda: int(time.time())),
            transport=transport,
            http_client=http_client,
        )

        self.me = AsyncMeResource(self._http)
        self.channels = AsyncChannelsResource(self._http)
        self.endpoints = AsyncEndpointsResource(self._http)
        self.template_variables = AsyncTemplateVariablesResource(self._http)
        self.messages = AsyncMessagesResource(self._http)
        self.contacts = AsyncContactsResource(self._http)
        self.contact_groups = AsyncContactGroupsResource(self._http)
        self.pipelines = AsyncPipelinesResource(self._http)
        self.leads = AsyncLeadsResource(self._http)
        self.broadcasts = AsyncBroadcastsResource(self._http)
        self.followups = AsyncFollowupsResource(self._http)
        self.webhooks = AsyncWebhooksResource(self._http)

    async def request(
        self,
        method: str,
        path: str,
        query: Optional[Query] = None,
        body: Any = None,
        idempotency_key: Optional[str] = None,
        *,
        options: Optional[RequestOptions] = None,
    ) -> ApiResponse[Any]:
        """Escape hatch for endpoints without a resource method yet."""
        return await self._http.request(method, path, query, body, idempotency_key, options=options)

    async def aclose(self) -> None:
        await self._http.aclose()

    async def __aenter__(self) -> "AsyncClient":
        return self

    async def __aexit__(self, *_exc: object) -> None:
        await self.aclose()
