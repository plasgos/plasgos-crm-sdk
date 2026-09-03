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
    "TransportError",
    "WebhookVerificationError",
    "error_from_response",
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
    ) -> None:
        super().__init__(message)
        self.message = message
        self.http_status = http_status
        self.api_status = api_status
        self.errors: list[dict[str, Any]] = list(errors or [])
        self.raw = raw
        self.request_id = request_id


class AuthenticationError(PlasgosApiError):
    """401 — missing or invalid authentication headers."""


class PermissionError(PlasgosApiError):  # noqa: A001 - deliberately shadows builtin in this namespace
    """403 — valid credentials but scope / context not allowed."""


class PlanError(PermissionError):
    """403 — the subscription plan does not include API access."""


class NotFoundError(PlasgosApiError):
    """404 — resource not found."""


class SignatureExpiredError(PlasgosApiError):
    """408 — x-timestamp outside the allowed window (clock skew)."""


class ConflictError(PlasgosApiError):
    """409 — duplicate message_id."""


class ValidationError(PlasgosApiError):
    """400 / 422 — request body failed validation; see ``.errors``."""


class BadRequestError(PlasgosApiError):
    """400 — generic bad request."""


class RateLimitError(PlasgosApiError):
    """429 — daily API hit quota exceeded."""


class ServerError(PlasgosApiError):
    """>= 500 — server-side failure."""


class TransportError(PlasgosApiError):
    """Network failure / timeout / DNS — no response was received."""


class WebhookVerificationError(Exception):
    """Raised by :func:`plasgos_crm.webhooks.verify_webhook` on token mismatch or bad JSON."""


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
