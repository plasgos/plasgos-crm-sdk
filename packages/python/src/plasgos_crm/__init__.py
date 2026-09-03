"""Official Python SDK for the Plasgos CRM Api Integration API."""

from __future__ import annotations

from ._envelope import Envelope
from ._http import BASE_URLS, resolve_base_url
from ._signature import compute_signature, current_timestamp, signature_headers
from .client import PlasgosCrmClient
from .credentials_client import PlasgosCrmCredentialsClient
from .errors import (
    AuthenticationError,
    BadRequestError,
    ConflictError,
    NotFoundError,
    PermissionError,
    PlanError,
    PlasgosApiError,
    RateLimitError,
    ServerError,
    SignatureExpiredError,
    TransportError,
    ValidationError,
    WebhookVerificationError,
)
from .webhooks import MESSAGE_RECEIVED, MESSAGE_STATUSES, verify_webhook

__version__ = "1.0.0"

__all__ = [
    "__version__",
    "PlasgosCrmClient",
    "PlasgosCrmCredentialsClient",
    "Envelope",
    "BASE_URLS",
    "resolve_base_url",
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
    "TransportError",
    "WebhookVerificationError",
]
