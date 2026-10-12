"""Typed errors. See docs/SDK_SPEC.md §5."""

from __future__ import annotations

import re
from typing import Any, Sequence

__all__ = [
    "PlasgosApiError",
    "AuthenticationError",
    "PermissionError",
    "PlanError",
    "NotFoundError",
    "SignatureExpiredError",
    "ConflictError",
    "ValidationError",
    "BadRequestError",
    "RateLimitError",
    "ServerError",
    "ChannelError",
    "ServiceUnavailableError",
    "TransportError",
    "WebhookVerificationError",
    "error_from_response",
    "error_from_v3",
]


class PlasgosApiError(Exception):
    """Base class for every error raised by the SDK."""

    def __init__(
        self,
        message: str,
        *,
        http_status: int = 0,
        api_status: int | str | None = None,
        errors: Sequence[dict[str, Any]] | None = None,
        raw: Any = None,
        request_id: str | None = None,
        error_code: str | None = None,
        error_type: str | None = None,
        details: Any = None,
        fields: Sequence[dict[str, str]] | None = None,
        doc_url: str | None = None,
        retry_after: int | None = None,
    ) -> None:
        super().__init__(message)
        self.message = message
        self.http_status = http_status
        self.api_status = api_status
        self.errors: list[dict[str, Any]] = list(errors or [])
        self.raw = raw
        self.request_id = request_id
        #: v3: stable machine code, e.g. ``requires_template`` — branch on this.
        self.error_code = error_code
        #: v3: error family, e.g. ``invalid_request``.
        self.error_type = error_type
        #: v3: context for screens, e.g. the conflicting contact id.
        self.details = details
        #: v3: per-field validation errors ``[{field, code, message}]``.
        self.fields: list[dict[str, str]] = list(fields or [])
        self.doc_url = doc_url
        #: Seconds from the ``Retry-After`` header.
        self.retry_after = retry_after


class AuthenticationError(PlasgosApiError):
    """401 — missing or invalid authentication headers."""


class PermissionError(PlasgosApiError):  # noqa: A001 - deliberately shadows builtin in this namespace
    """403 — valid credentials but scope / context not allowed."""


class PlanError(PermissionError):
    """403 — the subscription plan does not include API access."""


class NotFoundError(PlasgosApiError):
    """404 — resource not found."""


class SignatureExpiredError(AuthenticationError):
    """Clock skew: v3 ``401 signature_expired`` (see ``details["server_time"]``), legacy 408."""


class ConflictError(PlasgosApiError):
    """409 — e.g. ``identity_taken``, ``idempotency_in_progress``; legacy duplicate message_id."""


class ValidationError(PlasgosApiError):
    """400 / 422 — request body failed validation; see ``.errors``."""


class BadRequestError(PlasgosApiError):
    """400 — generic bad request."""


class RateLimitError(PlasgosApiError):
    """429 — ``rate_limited`` (retried automatically) or ``daily_quota_exceeded``."""


class ServerError(PlasgosApiError):
    """>= 500 — server-side failure."""


class ChannelError(ServerError):
    """502 — the channel provider (e.g. Meta) failed. Retried automatically."""


class ServiceUnavailableError(ServerError):
    """503 — temporarily unavailable. Retried automatically."""


class TransportError(PlasgosApiError):
    """Network failure / timeout / DNS — no response was received (``error_code = transport_error``)."""

    def __init__(self, message: str, **kwargs: Any) -> None:
        kwargs.setdefault("error_code", "transport_error")
        super().__init__(message, **kwargs)


class WebhookVerificationError(Exception):
    """Raised by webhook verification (v3 :class:`Webhook`, legacy ``x-hub-token``)."""


_PLAN_RE = re.compile(r"tidak menyertakan|does not include api|plan", re.IGNORECASE)


def error_from_response(
    *,
    http_status: int,
    api_status: int | str | None,
    message: str,
    errors: Sequence[dict[str, Any]],
    raw: Any,
    request_id: str | None,
) -> PlasgosApiError:
    kwargs: dict[str, Any] = dict(
        http_status=http_status,
        api_status=api_status,
        errors=errors,
        raw=raw,
        request_id=request_id,
    )
    msg = message or f"Request failed with status {http_status}"

    if http_status == 401:
        return AuthenticationError(msg, **kwargs)
    if http_status == 403:
        if _PLAN_RE.search(message or ""):
            return PlanError(msg, **kwargs)
        return PermissionError(msg, **kwargs)
    if http_status == 404:
        return NotFoundError(msg, **kwargs)
    if http_status == 408:
        return SignatureExpiredError(msg, **kwargs)
    if http_status == 409:
        return ConflictError(msg, **kwargs)
    if http_status == 422:
        return ValidationError(msg, **kwargs)
    if http_status == 429:
        return RateLimitError(msg, **kwargs)
    if http_status == 400:
        return ValidationError(msg, **kwargs) if errors else BadRequestError(msg, **kwargs)
    if http_status >= 500:
        return ServerError(msg, **kwargs)
    return PlasgosApiError(msg, **kwargs)


def error_from_v3(
    *, http_status: int, body: dict[str, Any] | None, request_id: str | None, retry_after: int | None
) -> PlasgosApiError:
    """Build a typed error from an API v3 error response (docs/SDK_SPEC.md §5)."""
    error: dict[str, Any] = body["error"] if body is not None and isinstance(body.get("error"), dict) else {}
    message = body.get("message") if body is not None else None
    message = message if isinstance(message, str) and message else f"Plasgos CRM responded with HTTP {http_status}"
    code = error.get("code") if isinstance(error.get("code"), str) else ("unexpected_response" if body is None else f"http_{http_status}")
    fields = [
        {"field": str(f.get("field", "")), "code": str(f.get("code", "")), "message": str(f.get("message", ""))}
        for f in (error["fields"] if isinstance(error.get("fields"), list) else [])
        if isinstance(f, dict)
    ]
    meta: dict[str, Any] = body["meta"] if body is not None and isinstance(body.get("meta"), dict) else {}
    kwargs: dict[str, Any] = dict(
        http_status=http_status,
        api_status=code,
        errors=[{"message": f["message"], "path": f["field"].split(".") if f["field"] else []} for f in fields],
        raw=body,
        request_id=meta.get("request_id") if isinstance(meta.get("request_id"), str) else request_id,
        error_code=code,
        error_type=error.get("type") if isinstance(error.get("type"), str) else None,
        details=error.get("details") if isinstance(error.get("details"), (dict, list)) else None,
        fields=fields,
        doc_url=error.get("doc_url") if isinstance(error.get("doc_url"), str) else None,
        retry_after=retry_after,
    )

    if http_status == 401 and code == "signature_expired":
        return SignatureExpiredError(message, **kwargs)
    if http_status == 401:
        return AuthenticationError(message, **kwargs)
    if http_status == 403 and code == "not_entitled":
        return PlanError(message, **kwargs)
    if http_status == 403:
        return PermissionError(message, **kwargs)
    if http_status == 404:
        return NotFoundError(message, **kwargs)
    if http_status == 409:
        return ConflictError(message, **kwargs)
    if http_status == 400 and not fields:
        return BadRequestError(message, **kwargs)
    if http_status in (400, 413, 422):
        return ValidationError(message, **kwargs)
    if http_status == 429:
        return RateLimitError(message, **kwargs)
    if http_status == 502:
        return ChannelError(message, **kwargs)
    if http_status == 503:
        return ServiceUnavailableError(message, **kwargs)
    if http_status >= 500:
        return ServerError(message, **kwargs)
    return PlasgosApiError(message, **kwargs)
