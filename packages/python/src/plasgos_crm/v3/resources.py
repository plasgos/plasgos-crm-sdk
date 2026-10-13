"""API v3 resources (docs/SDK_SPEC.md §3).

Inputs and outputs use the wire format (snake_case); the OpenAPI schemas
(``openapi/openapi-v3.json``) describe them. Query parameters may be given as a mapping, as keyword
arguments, or both (``client.contacts.list(per_page=100, tag="vip")``). Every method also takes a
keyword-only ``options`` (:class:`~plasgos_crm.v3.RequestOptions`: timeout, max_retries, headers).
"""

from __future__ import annotations

from typing import Any, Mapping, Optional

from . import types as _t
from ._api_client import ApiClient, QueryValue, RequestOptions, segment
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

def _path(*parts: str) -> str:
    """``("contacts", id)`` → ``contacts/<encoded id>``: every second part is an id."""
    return "/".join(segment(part) if i % 2 == 1 else part for i, part in enumerate(parts))


def _query(params: Optional[Mapping[str, Any]], extra: Mapping[str, QueryValue]) -> dict[str, QueryValue]:
    return {**(params or {}), **extra}


class _Resource:
    def __init__(self, http: ApiClient) -> None:
        self._http = http


class MeResource(_Resource):
    """``client.me`` — scope ``account:read``."""

    def get(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetMeData]:
        return self._http.request("GET", "/me", options=options)


class ChannelsResource(_Resource):
    """``client.channels`` — scope ``channels:read``."""

    def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListChannelsData]:
        return self._http.request("GET", "/channels", options=options)


class EndpointsResource(_Resource):
    """``client.endpoints`` — sending endpoints (one per number/page/account). Scope ``channels:read``."""

    def list(self, params: Optional[_t.ListEndpointsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListEndpointsItem]:
        return self._http.request_page("/endpoints", _query(params, kwargs), options)

    def get(self, endpoint_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetEndpointData]:
        return self._http.request("GET", _path("endpoints", endpoint_id), options=options)

    def templates(self, endpoint_id: str, params: Optional[_t.ListEndpointTemplatesQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListEndpointTemplatesItem]:
        """Approved WhatsApp templates of an official endpoint, with the variables to map."""
        return self._http.request_page(f"{_path('endpoints', endpoint_id)}/templates", _query(params, kwargs), options)


class TemplateVariablesResource(_Resource):
    """``client.template_variables`` — global placeholders such as ``{{full_name}}``. Scope ``channels:read``."""

    def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListTemplateVariablesData]:
        return self._http.request("GET", "/template-variables", options=options)


class MessagesResource(_Resource):
    """``client.messages`` — omnichannel sending. Scopes ``messages:read`` / ``messages:write``."""

    def send_options(self, contact: Optional[_t.GetMessageSendOptionsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> ApiResponse[_t.GetMessageSendOptionsData]:
        """Endpoints that can reach this contact.

        Exactly one of ``contact_id``, ``phone_number``, ``channel`` + ``external_id``, or ``email`` (+ ``endpoint_id``).
        """
        return self._http.request("GET", "/messages/send-options", _query(contact, kwargs), options=options)

    def send(self, message: _t.SendMessageBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.SendMessageData]:
        """202. A message the channel rejected is still 202 with ``status = failed`` and ``error``."""
        return self._http.request("POST", "/messages", None, message, idempotency_key, options=options)

    def get(self, message_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetMessageData]:
        return self._http.request("GET", _path("messages", message_id), options=options)


class ContactsResource(_Resource):
    """``client.contacts`` — scopes ``contacts:read`` / ``contacts:write``."""

    def list(self, params: Optional[_t.ListContactsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListContactsItem]:
        return self._http.request_page("/contacts", _query(params, kwargs), options)

    def lookup(self, by: Optional[_t.LookupContactQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> ApiResponse[_t.LookupContactData]:
        """Exactly one of ``phone_number``, ``email``, or ``channel`` + ``external_id`` (+ ``endpoint_id``).

        Not found → :class:`~plasgos_crm.errors.NotFoundError`.
        """
        return self._http.request("GET", "/contacts/lookup", _query(by, kwargs), options=options)

    def create(self, contact: _t.CreateContactBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateContactData]:
        """Existing phone/email → ``ConflictError`` ``identity_taken`` (``details["contact_id"]``)."""
        return self._http.request("POST", "/contacts", None, contact, idempotency_key, options=options)

    def upsert(self, contact: _t.UpsertContactBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UpsertContactData]:
        """201 created / 200 updated (``meta["created"]``). Tags and group ids are ADDED; may include ``match_on``."""
        return self._http.request("POST", "/contacts/upsert", None, contact, idempotency_key, options=options)

    def get(self, contact_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetContactData]:
        """A merged (source) contact id returns the target contact with ``meta["redirected_from"]``."""
        return self._http.request("GET", _path("contacts", contact_id), options=options)

    def update(self, contact_id: str, changes: _t.UpdateContactBody, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UpdateContactData]:
        """``tags`` / ``group_ids`` take ``{"add": [...], "remove": [...]}``."""
        return self._http.request("PATCH", _path("contacts", contact_id), None, changes, options=options)


class ContactGroupsResource(_Resource):
    """``client.contact_groups`` — broadcast audiences. Scope ``contacts:read``."""

    def list(self, params: Optional[_t.ListContactGroupsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListContactGroupsItem]:
        return self._http.request_page("/contact-groups", _query(params, kwargs), options)


class PipelinesResource(_Resource):
    """``client.pipelines`` — lead pipelines with their stages. Scope ``leads:read``."""

    def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListPipelinesData]:
        return self._http.request("GET", "/pipelines", options=options)


class LeadsResource(_Resource):
    """``client.leads`` — scopes ``leads:read`` / ``leads:write``."""

    def create(self, lead: _t.CreateLeadBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateLeadData]:
        """201 new lead; 200 with ``meta["created"] is False`` when the contact already has an active lead."""
        return self._http.request("POST", "/leads", None, lead, idempotency_key, options=options)

    def get(self, lead_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetLeadData]:
        return self._http.request("GET", _path("leads", lead_id), options=options)


class BroadcastsResource(_Resource):
    """``client.broadcasts`` — official WhatsApp template broadcasts. Scopes ``broadcasts:read`` / ``broadcasts:write``."""

    def list(self, params: Optional[_t.ListBroadcastsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListBroadcastsItem]:
        return self._http.request_page("/broadcasts", _query(params, kwargs), options)

    def create(self, broadcast: _t.CreateBroadcastBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateBroadcastData]:
        """202. Rejected recipients do not fail the request: see ``meta["rejected_recipients"]``."""
        return self._http.request("POST", "/broadcasts", None, broadcast, idempotency_key, options=options)

    def get(self, broadcast_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetBroadcastData]:
        return self._http.request("GET", _path("broadcasts", broadcast_id), options=options)

    def recipients(self, broadcast_id: str, params: Optional[_t.ListBroadcastRecipientsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListBroadcastRecipientsItem]:
        return self._http.request_page(f"{_path('broadcasts', broadcast_id)}/recipients", _query(params, kwargs), options)

    def cancel(self, broadcast_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CancelBroadcastData]:
        """Completed/cancelled → ``ConflictError`` ``broadcast_not_cancellable``."""
        return self._http.request("POST", f"{_path('broadcasts', broadcast_id)}/cancel", None, {}, options=options)


class FollowupsResource(_Resource):
    """``client.followups`` — enrol contacts into sequences designed in the dashboard.

    Scopes ``followups:read`` / ``followups:write``.
    """

    def list(self, params: Optional[_t.ListFollowupsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListFollowupsItem]:
        return self._http.request_page("/followups", _query(params, kwargs), options)

    def get(self, followup_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetFollowupData]:
        return self._http.request("GET", _path("followups", followup_id), options=options)

    def enrollments(self, followup_id: str, params: Optional[_t.ListFollowupEnrollmentsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> Page[_t.ListFollowupEnrollmentsItem]:
        return self._http.request_page(f"{_path('followups', followup_id)}/enrollments", _query(params, kwargs), options)

    def enroll(self, followup_id: str, enrollment: _t.EnrollFollowupBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.EnrollFollowupData]:
        """201 enrolled; 200 with ``meta["created"] is False`` when still actively enrolled."""
        return self._http.request(
            "POST", f"{_path('followups', followup_id)}/enrollments", None, enrollment, idempotency_key, options=options
        )

    def unenroll(self, followup_id: str, contact_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UnenrollFollowupData]:
        """Stops the sequence for one contact (idempotent). Never enrolled → ``NotFoundError`` ``enrollment_not_found``."""
        return self._http.request("DELETE", f"{_path('followups', followup_id)}/enrollments/{segment(contact_id)}", options=options)


class WebhooksResource(_Resource):
    """``client.webhooks`` — subscriptions for this tenant. Scope ``webhooks:manage``."""

    def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListWebhooksData]:
        return self._http.request("GET", "/webhooks", options=options)

    def create(self, subscription: _t.CreateWebhookBody, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateWebhookData]:
        """``data["signing_secret"]`` is returned ONLY here — store it. https + public URL, max 5."""
        return self._http.request("POST", "/webhooks", None, subscription, options=options)

    def update(self, webhook_id: str, changes: _t.UpdateWebhookBody, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UpdateWebhookData]:
        return self._http.request("PATCH", _path("webhooks", webhook_id), None, changes, options=options)

    def delete(self, webhook_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.DeleteWebhookData]:
        return self._http.request("DELETE", _path("webhooks", webhook_id), options=options)

    def test(self, webhook_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.TestWebhookData]:
        """Ask the CRM to deliver a ``webhook.test`` event now."""
        return self._http.request("POST", f"{_path('webhooks', webhook_id)}/test", None, {}, options=options)

    def rotate_secret(self, webhook_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.RotateWebhookSecretData]:
        """New signing secret; the previous one stays valid for 24 hours (two signatures meanwhile)."""
        return self._http.request("POST", f"{_path('webhooks', webhook_id)}/rotate-secret", None, {}, options=options)
