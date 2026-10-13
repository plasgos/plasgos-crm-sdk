"""HTTP layer for API v3 (``/api/v3/*``): signing, idempotency, retry policy, envelope parsing.

See docs/SDK_SPEC.md §2, §4–§6.
"""

from __future__ import annotations

import json
import random
import re
import uuid
from typing import Any, Callable, Mapping, Optional, Union
from urllib.parse import quote

import httpx

from ..errors import (
    ChannelError,
    PlasgosApiError,
    RateLimitError,
    ServerError,
    ServiceUnavailableError,
    TransportError,
    error_from_v3,
)
from .response import ApiResponse, Page, RateLimit
from .signature import signature_headers_v3

__all__ = ["ApiClient", "parse", "build_query", "segment", "QueryValue", "Query"]

QueryValue = Union[str, int, float, bool, None]
Query = Mapping[str, QueryValue]

PREFIX = "/api/v3"
_RETRY_BASE = 0.5
_RETRY_CAP = 8.0
_SCHEME_HOST = re.compile(r"^[a-z][a-z0-9+.-]*://[^/]+", re.IGNORECASE)


class ApiClient:
    def __init__(
        self,
        *,
        base_url: str,
        api_key: str,
        secret_key: str,
        user_agent: str,
        max_retries: int,
        max_retry_wait: float,
        timeout: float,
        sleeper: Callable[[float], None],
        clock: Callable[[], int],
        transport: Optional[httpx.BaseTransport] = None,
        http_client: Optional[httpx.Client] = None,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._api_key = api_key
        self._secret_key = secret_key
        self._user_agent = user_agent
        self._max_retries = max_retries
        self._max_retry_wait = max_retry_wait
        self._sleeper = sleeper
        self._clock = clock
        self._owns_client = http_client is None
        self._client = http_client or httpx.Client(timeout=timeout, transport=transport, follow_redirects=False)

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def request(
        self,
        method: str,
        path: str,
        query: Optional[Query] = None,
        body: Any = None,
        idempotency_key: Optional[str] = None,
    ) -> ApiResponse[Any]:
        verb = method.upper()
        query_string = build_query(query or {})
        url = f"{self._base_url}{PREFIX}/{path.lstrip('/')}" + (f"?{query_string}" if query_string else "")
        # Path + query exactly as sent — including any sub-path of a custom base URL.
        request_target = _SCHEME_HOST.sub("", url) or "/"
        # Serialised ONCE: the exact bytes signed are the bytes sent.
        raw_body = b"" if body is None else _encode_body(body)
        # One key per intent: generated once here and reused by every retry.
        key = idempotency_key if idempotency_key is not None else (str(uuid.uuid4()) if verb == "POST" else None)

        attempt = 0
        while True:
            try:
                return self._attempt(verb, url, request_target, raw_body, key)
            except PlasgosApiError as error:
                wait = self._retry_wait(error, verb, attempt)
                if wait is None:
                    raise
                self._sleeper(wait)
                attempt += 1

    def request_page(self, path: str, query: Optional[Query] = None) -> Page[Any]:
        params = dict(query or {})
        response = self.request("GET", path, params)
        return Page(response, lambda page: self.request_page(path, {**params, "page": page}))

    def _attempt(
        self, method: str, url: str, request_target: str, raw_body: bytes, idempotency_key: Optional[str]
    ) -> ApiResponse[Any]:
        headers = {
            "Accept": "application/json",
            "User-Agent": self._user_agent,
            **signature_headers_v3(self._api_key, self._secret_key, method, request_target, raw_body, self._clock()),
        }
        if idempotency_key is not None:
            headers["Idempotency-Key"] = idempotency_key
        if raw_body:
            headers["Content-Type"] = "application/json; charset=utf-8"

        try:
            response = self._client.request(method, url, content=raw_body or None, headers=headers)
        except httpx.TimeoutException as err:
            raise TransportError(f"Request to {url} timed out", raw=err) from err
        except httpx.HTTPError as err:
            raise TransportError(f"Network error requesting {url}: {err}", raw=err) from err
        return parse(response.status_code, {k.lower(): v for k, v in response.headers.items()}, response.text)

    def _retry_wait(self, error: PlasgosApiError, method: str, attempt: int) -> Optional[float]:
        """Seconds to wait before retrying, or ``None`` when this error is not retried (§6)."""
        if attempt >= self._max_retries:
            return None
        if isinstance(error, RateLimitError):
            # Daily quota does not recover in seconds — hand it back to the caller.
            if error.error_code != "rate_limited":
                return None
            if error.retry_after is not None:
                return float(error.retry_after) if error.retry_after <= self._max_retry_wait else None
            return _backoff(attempt)
        # The first request with this Idempotency-Key is still running (e.g. it timed out client-side);
        # retrying with the same key returns its stored answer once it finishes.
        if error.error_code == "idempotency_in_progress":
            return _backoff(attempt)
        transient = isinstance(error, (TransportError, ChannelError, ServiceUnavailableError))
        # A plain 500 may have partially executed; only reads are retried on it.
        if transient or (method == "GET" and isinstance(error, ServerError)):
            return _backoff(attempt)
        return None


def parse(status: int, headers: Mapping[str, str], text: str) -> ApiResponse[Any]:
    """Parse a v3 response or raise a typed error (§4, §5). ``headers`` keys are lower-case."""
    request_id = headers.get("x-request-id")
    body: Optional[dict[str, Any]] = None
    if text != "":
        try:
            decoded = json.loads(text)
            body = decoded if isinstance(decoded, dict) else None
        except ValueError:
            body = None

    if status < 200 or status >= 300:
        retry_after_header = headers.get("retry-after", "")
        retry_after = int(retry_after_header) if retry_after_header.isdigit() else None
        raise error_from_v3(http_status=status, body=body, request_id=request_id, retry_after=retry_after)
    if text != "" and (body is None or "data" not in body):
        raise PlasgosApiError(
            "Plasgos CRM response is not an API v3 envelope",
            http_status=status,
            raw=text,
            request_id=request_id,
            error_code="unexpected_response",
            error_type="api_error",
        )

    meta = body.get("meta") if body is not None and isinstance(body.get("meta"), dict) else {}
    assert isinstance(meta, dict)
    message = body.get("message") if body is not None else None
    return ApiResponse(
        http_status=status,
        message=message if isinstance(message, str) else "",
        data=body.get("data") if body is not None else None,
        meta=meta,
        request_id=meta["request_id"] if isinstance(meta.get("request_id"), str) else request_id,
        rate_limit=RateLimit(
            limit=_int(headers.get("ratelimit-limit")),
            remaining=_int(headers.get("ratelimit-remaining")),
            reset=_int(headers.get("ratelimit-reset")),
        ),
        replayed=headers.get("idempotent-replayed", "").lower() == "true",
        headers=dict(headers),
    )


def build_query(query: Query) -> str:
    """RFC 3986 query: ``None``/``""`` dropped, booleans as ``true``/``false``, spaces as ``%20`` (§2)."""
    parts = []
    for key, value in query.items():
        if value is None or value == "":
            continue
        text = ("true" if value else "false") if isinstance(value, bool) else str(value)
        parts.append(f"{quote(str(key), safe='')}={quote(text, safe='')}")
    return "&".join(parts)


def segment(value: str) -> str:
    """Path segment, URL-encoded so an id can never alter the path."""
    return quote(str(value), safe="")


def _encode_body(body: Any) -> bytes:
    return json.dumps(body, ensure_ascii=False, separators=(",", ":"), default=_json_default).encode("utf-8")


def _json_default(value: Any) -> Any:
    to_dict = getattr(value, "to_dict", None)
    if callable(to_dict):
        return to_dict()
    raise TypeError(f"Object of type {type(value).__name__} is not JSON serializable")


def _int(value: Optional[str]) -> Optional[int]:
    if value is None or value == "":
        return None
    try:
        return int(value)
    except ValueError:
        return None


def _backoff(attempt: int) -> float:
    cap = min(_RETRY_CAP, _RETRY_BASE * (2**attempt))
    return random.uniform(0, cap)  # full jitter
