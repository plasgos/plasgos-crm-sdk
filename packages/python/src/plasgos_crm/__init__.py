"""Official Python SDK for the Plasgos CRM Api Integration API.

SDK 2.x: :class:`Client` talks to API v3 (``/api/v3/*``). :class:`LegacyClient` (``/api/v1/*``)
is deprecated and removed in 3.0.
"""

from __future__ import annotations

from ._envelope import Envelope
from ._http import BASE_URLS, resolve_base_url
from ._signature import compute_signature, current_timestamp, signature_headers
from ._version import __version__
from .client import LegacyClient, PlasgosCrmClient
from .credentials_client import PlasgosCrmCredentialsClient
from .errors import (
    AuthenticationError,
    BadRequestError,
    ChannelError,
    ConflictError,
    NotFoundError,
    PermissionError,
    PlanError,
    PlasgosApiError,
    RateLimitError,
    ServerError,
    ServiceUnavailableError,
    SignatureExpiredError,
    TransportError,
    ValidationError,
    WebhookVerificationError,
    error_from_v3,
)
from .v3 import (
    ApiResponse,
    Client,
    Content,
    Page,
    RateLimit,
    RequestOptions,
    TemplateContent,
    Webhook,
    WebhookEventType,
    build_query,
    sign_v3,
    signature_headers_v3,
    string_to_sign,
)
from .webhooks import MESSAGE_RECEIVED, MESSAGE_STATUSES, verify_webhook

__all__ = [
    "__version__",
    # API v3 (SDK 2.x)
    "Client",
    "ApiResponse",
    "Page",
    "RateLimit",
    "RequestOptions",
    "Content",
    "TemplateContent",
    "Webhook",
    "WebhookEventType",
    "sign_v3",
    "signature_headers_v3",
    "string_to_sign",
    "build_query",
    "BASE_URLS",
    "resolve_base_url",
    # legacy /api/v1 (deprecated, removed in 3.0)
    "LegacyClient",
    "PlasgosCrmClient",
    "PlasgosCrmCredentialsClient",
    "Envelope",
    "compute_signature",
    "current_timestamp",
    "signature_headers",
    "verify_webhook",
    "MESSAGE_RECEIVED",
    "MESSAGE_STATUSES",
    # errors
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
    "error_from_v3",
]
