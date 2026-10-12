"""API v3 resources (docs/SDK_SPEC.md §3).

Inputs and outputs use the wire format (snake_case); the OpenAPI schemas
(``openapi/openapi-v3.json``) describe them. Query parameters may be given as a mapping, as keyword
arguments, or both (``client.contacts.list(per_page=100, tag="vip")``).
"""

from __future__ import annotations

from typing import Any, Mapping, Optional

from ._api_client import ApiClient, Query, QueryValue, segment
from .response import ApiResponse, Page

__all__ = [
    "MeResource",
    "ChannelsResource",
    "EndpointsResource",
    "TemplateVariablesResource",
    "MessagesResource",
    "ContactsResource",
    "ContactGroupsResource",
    "PipelinesResource",
    "LeadsResource",
    "BroadcastsResource",
    "FollowupsResource",
    "WebhooksResource",
]

Body = Mapping[str, Any]


def _path(*parts: str) -> str:
    """``("contacts", id)`` → ``contacts/<encoded id>``: every second part is an id."""
    return "/".join(segment(part) if i % 2 == 1 else part for i, part in enumerate(parts))


def _query(params: Optional[Query], extra: Mapping[str, QueryValue]) -> dict[str, QueryValue]:
    return {**(params or {}), **extra}


class _Resource:
    def __init__(self, http: ApiClient) -> None:
        self._http = http


class MeResource(_Resource):
    """``client.me`` — scope ``account:read``."""

    def get(self) -> ApiResponse:
        return self._http.request("GET", "/me")


class ChannelsResource(_Resource):
    """``client.channels`` — scope ``channels:read``."""

    def list(self) -> ApiResponse:
        return self._http.request("GET", "/channels")


class EndpointsResource(_Resource):
    """``client.endpoints`` — sending endpoints (one per number/page/account). Scope ``channels:read``."""

    def list(self, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page("/endpoints", _query(params, kwargs))

    def get(self, endpoint_id: str) -> ApiResponse:
        return self._http.request("GET", _path("endpoints", endpoint_id))

    def templates(self, endpoint_id: str, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        """Approved WhatsApp templates of an official endpoint, with the variables to map."""
        return self._http.request_page(f"{_path('endpoints', endpoint_id)}/templates", _query(params, kwargs))


class TemplateVariablesResource(_Resource):
    """``client.template_variables`` — global placeholders such as ``{{full_name}}``. Scope ``channels:read``."""

    def list(self) -> ApiResponse:
        return self._http.request("GET", "/template-variables")


class MessagesResource(_Resource):
    """``client.messages`` — omnichannel sending. Scopes ``messages:read`` / ``messages:write``."""

    def send_options(self, recipient: Optional[Query] = None, **kwargs: QueryValue) -> ApiResponse:
        """Endpoints that can reach this recipient.

        Exactly one of ``contact_id``, ``phone_number``, ``channel`` + ``external_id`` (+ ``endpoint_id``).
        """
        return self._http.request("GET", "/messages/send-options", _query(recipient, kwargs))

    def send(self, message: Body, idempotency_key: Optional[str] = None) -> ApiResponse:
        """202. A message the channel rejected is still 202 with ``status = failed`` and ``error``."""
        return self._http.request("POST", "/messages", None, message, idempotency_key)

    def get(self, message_id: str) -> ApiResponse:
        return self._http.request("GET", _path("messages", message_id))


class ContactsResource(_Resource):
    """``client.contacts`` — scopes ``contacts:read`` / ``contacts:write``."""

    def list(self, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page("/contacts", _query(params, kwargs))

    def lookup(self, by: Optional[Query] = None, **kwargs: QueryValue) -> ApiResponse:
        """Exactly one of ``phone_number``, ``email``, or ``channel`` + ``external_id`` (+ ``endpoint_id``).

        Not found → :class:`~plasgos_crm.errors.NotFoundError`.
        """
        return self._http.request("GET", "/contacts/lookup", _query(by, kwargs))

    def create(self, contact: Body, idempotency_key: Optional[str] = None) -> ApiResponse:
        """Existing phone/email → ``ConflictError`` ``identity_taken`` (``details["contact_id"]``)."""
        return self._http.request("POST", "/contacts", None, contact, idempotency_key)

    def upsert(self, contact: Body, idempotency_key: Optional[str] = None) -> ApiResponse:
        """201 created / 200 updated (``meta["operation"]``). Tags and group ids are ADDED; may include ``match_on``."""
        return self._http.request("POST", "/contacts/upsert", None, contact, idempotency_key)

    def get(self, contact_id: str) -> ApiResponse:
        """A merged (source) contact id returns the target contact with ``meta["redirected_from"]``."""
        return self._http.request("GET", _path("contacts", contact_id))

    def update(self, contact_id: str, changes: Body) -> ApiResponse:
        """``tags`` / ``group_ids`` take ``{"add": [...], "remove": [...]}``."""
        return self._http.request("PATCH", _path("contacts", contact_id), None, changes)


class ContactGroupsResource(_Resource):
    """``client.contact_groups`` — broadcast audiences. Scope ``contacts:read``."""

    def list(self, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page("/contact-groups", _query(params, kwargs))


class PipelinesResource(_Resource):
    """``client.pipelines`` — lead pipelines with their stages. Scope ``leads:read``."""

    def list(self) -> ApiResponse:
        return self._http.request("GET", "/pipelines")


class LeadsResource(_Resource):
    """``client.leads`` — scopes ``leads:read`` / ``leads:write``."""

    def create(self, lead: Body, idempotency_key: Optional[str] = None) -> ApiResponse:
        """201 new lead; 200 with ``meta["created"] is False`` when the contact already has an active lead."""
        return self._http.request("POST", "/leads", None, lead, idempotency_key)

    def get(self, lead_id: str) -> ApiResponse:
        return self._http.request("GET", _path("leads", lead_id))


class BroadcastsResource(_Resource):
    """``client.broadcasts`` — official WhatsApp template broadcasts. Scopes ``broadcasts:read`` / ``broadcasts:write``."""

    def list(self, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page("/broadcasts", _query(params, kwargs))

    def create(self, broadcast: Body, idempotency_key: Optional[str] = None) -> ApiResponse:
        """202. Rejected recipients do not fail the request: see ``meta["rejected_recipients"]``."""
        return self._http.request("POST", "/broadcasts", None, broadcast, idempotency_key)

    def get(self, broadcast_id: str) -> ApiResponse:
        return self._http.request("GET", _path("broadcasts", broadcast_id))

    def recipients(self, broadcast_id: str, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page(f"{_path('broadcasts', broadcast_id)}/recipients", _query(params, kwargs))

    def cancel(self, broadcast_id: str) -> ApiResponse:
        """Completed/cancelled → ``ConflictError`` ``broadcast_not_cancellable``."""
        return self._http.request("POST", f"{_path('broadcasts', broadcast_id)}/cancel", None, {})


class FollowupsResource(_Resource):
    """``client.followups`` — enrol contacts into sequences designed in the dashboard.

    Scopes ``followups:read`` / ``followups:write``.
    """

    def list(self, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page("/followups", _query(params, kwargs))

    def get(self, followup_id: str) -> ApiResponse:
        return self._http.request("GET", _path("followups", followup_id))

    def enrollments(self, followup_id: str, params: Optional[Query] = None, **kwargs: QueryValue) -> Page[Any]:
        return self._http.request_page(f"{_path('followups', followup_id)}/enrollments", _query(params, kwargs))

    def enroll(self, followup_id: str, enrollment: Body, idempotency_key: Optional[str] = None) -> ApiResponse:
        """201 enrolled; 200 with ``meta["created"] is False`` when still actively enrolled."""
        return self._http.request(
            "POST", f"{_path('followups', followup_id)}/enrollments", None, enrollment, idempotency_key
        )

    def unenroll(self, followup_id: str, contact_id: str) -> ApiResponse:
        """Stops the sequence for one contact (idempotent). Never enrolled → ``NotFoundError`` ``enrollment_not_found``."""
        return self._http.request("DELETE", f"{_path('followups', followup_id)}/enrollments/{segment(contact_id)}")


class WebhooksResource(_Resource):
    """``client.webhooks`` — subscriptions for this tenant. Scope ``webhooks:manage``."""

    def list(self) -> ApiResponse:
        return self._http.request("GET", "/webhooks")

    def create(self, subscription: Body) -> ApiResponse:
        """``data["signing_secret"]`` is returned ONLY here — store it. https + public URL, max 5."""
        return self._http.request("POST", "/webhooks", None, subscription)

    def update(self, webhook_id: str, changes: Body) -> ApiResponse:
        return self._http.request("PATCH", _path("webhooks", webhook_id), None, changes)

    def delete(self, webhook_id: str) -> ApiResponse:
        return self._http.request("DELETE", _path("webhooks", webhook_id))

    def test(self, webhook_id: str) -> ApiResponse:
        """Ask the CRM to deliver a ``webhook.test`` event now."""
        return self._http.request("POST", f"{_path('webhooks', webhook_id)}/test", None, {})

    def rotate_secret(self, webhook_id: str) -> ApiResponse:
        """New signing secret; the previous one stays valid for 24 hours (two signatures meanwhile)."""
        return self._http.request("POST", f"{_path('webhooks', webhook_id)}/rotate-secret", None, {})
