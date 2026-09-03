from __future__ import annotations

import json as _json
import random
import time
from typing import Any, Callable

import httpx

from ._envelope import Envelope
from .errors import (
    PlasgosApiError,
    RateLimitError,
    ServerError,
    TransportError,
    error_from_response,
)

__all__ = ["HttpClient", "normalize", "BASE_URLS", "resolve_base_url"]

BASE_URLS = {
    "production": "https://wa-client.plasgos.co.id",
    "sandbox": "https://api-crm.sandbox.plasgos.co.id",
}

_RETRY_BASE_MS = 500
_RETRY_CAP_MS = 8000


def resolve_base_url(value: str | None) -> str:
    if not value:
        return BASE_URLS["production"]
    if value in BASE_URLS:
        return BASE_URLS[value]
    return value.rstrip("/")


class HttpClient:
    def __init__(
        self,
        *,
        base_url: str,
        user_agent: str,
        max_retries: int,
        auth_headers: Callable[[], dict[str, str]],
        timeout: float = 15.0,
        transport: httpx.BaseTransport | None = None,
        client: httpx.Client | None = None,
    ) -> None:
        self._auth_headers = auth_headers
        self._max_retries = max_retries
        self._owns_client = client is None
        self._client = client or httpx.Client(
            base_url=base_url,
            timeout=timeout,
            transport=transport,
            headers={"User-Agent": user_agent, "Accept": "application/json"},
        )

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def __enter__(self) -> "HttpClient":
        return self

    def __exit__(self, *_exc: object) -> None:
        self.close()

    def request(
        self,
        method: str,
        path: str,
        *,
        query: dict[str, Any] | None = None,
        json: dict[str, Any] | None = None,
    ) -> Envelope:
        is_idempotent = method.upper() == "GET"
        max_attempts = self._max_retries + 1 if is_idempotent else 1
        params = {k: v for k, v in (query or {}).items() if v is not None}

        last_error: PlasgosApiError | None = None
        for attempt in range(max_attempts):
            try:
                return self._attempt(method, path, params, json)
            except PlasgosApiError as err:
                last_error = err
                retryable = (
                    is_idempotent
                    and attempt < max_attempts - 1
                    and isinstance(err, (TransportError, ServerError, RateLimitError))
                )
                if not retryable:
                    raise
                time.sleep(_backoff_seconds(attempt))
        assert last_error is not None
        raise last_error

    def _attempt(
        self,
        method: str,
        path: str,
        params: dict[str, Any],
        json: dict[str, Any] | None,
    ) -> Envelope:
        try:
            response = self._client.request(
                method.upper(),
                path,
                params=params,
                json=json,
                headers=self._auth_headers(),
            )
        except httpx.TimeoutException as err:
            raise TransportError(f"Request to {path} timed out") from err
        except httpx.HTTPError as err:
            raise TransportError(f"Network error requesting {path}: {err}") from err

        request_id = response.headers.get("x-request-id") or response.headers.get(
            "x-correlation-id"
        )
        return normalize(response.status_code, response.text, request_id)


def _backoff_seconds(attempt: int) -> float:
    exp = min(_RETRY_CAP_MS, _RETRY_BASE_MS * (2**attempt))
    return random.uniform(0, exp) / 1000.0


def normalize(http_status: int, body_text: str, request_id: str | None) -> Envelope:
    """Collapse the server's several envelope shapes into one, or raise a typed error."""
    body: Any = None
    if body_text:
        try:
            body = _json.loads(body_text)
        except ValueError:
            body = body_text

    obj = body if isinstance(body, dict) else None
    ok = 200 <= http_status < 300

    # { success, data } shape
    if obj is not None and "success" in obj and "status" not in obj:
        if obj.get("success") is True and ok:
            return Envelope(
                status=http_status,
                message=obj["message"] if isinstance(obj.get("message"), str) else None,
                data=obj.get("data"),
                metadata={},
                raw=body,
                http_status=http_status,
            )
        raise error_from_response(
            http_status=http_status,
            api_status=None,
            message=obj["message"] if isinstance(obj.get("message"), str) else "",
            errors=_extract_issues(obj),
            raw=body,
            request_id=request_id,
        )

    api_status = obj.get("status") if obj is not None else None
    message = obj["message"] if obj is not None and isinstance(obj.get("message"), str) else None
    api_status_is_error = isinstance(api_status, int) and not isinstance(api_status, bool) and api_status >= 400

    if not ok or api_status_is_error:
        raise error_from_response(
            http_status=api_status if (ok and api_status_is_error) else http_status,
            api_status=api_status,
            message=message or f"Request failed with status {http_status}",
            errors=_extract_issues(obj) if obj is not None else [],
            raw=body,
            request_id=request_id,
        )

    if obj is None:
        return Envelope(http_status, None, body, {}, body, http_status)

    metadata = obj["metadata"] if isinstance(obj.get("metadata"), dict) else (
        obj["meta"] if isinstance(obj.get("meta"), dict) else {}
    )
    return Envelope(
        status=api_status if isinstance(api_status, (int, bool)) else http_status,
        message=message,
        data=obj["data"] if "data" in obj else obj,
        metadata=metadata,
        raw=body,
        http_status=http_status,
    )


def _extract_issues(obj: dict[str, Any]) -> list[dict[str, Any]]:
    raw = obj.get("errors")
    if not isinstance(raw, list):
        return []
    out: list[dict[str, Any]] = []
    for item in raw:
        if isinstance(item, dict):
            issue: dict[str, Any] = {"message": str(item.get("message", ""))}
            if isinstance(item.get("path"), list):
                issue["path"] = item["path"]
            out.append(issue)
        else:
            out.append({"message": str(item)})
    return out
