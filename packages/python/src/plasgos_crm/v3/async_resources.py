"""API v3 resources for :class:`~plasgos_crm.AsyncClient` (docs/SDK_SPEC.md §3).

Mirror of :mod:`plasgos_crm.v3.resources` with ``async`` methods and :class:`AsyncPage` lists: same
names, same parameters (``tests/test_v3_async.py`` checks the parity).
"""

from __future__ import annotations

from typing import Optional

from . import types as _t
from ._api_client import AsyncApiClient, QueryValue, RequestOptions, segment
from .resources import _path, _query
from .response import ApiResponse, AsyncPage

__all__ = [
    "AsyncMeResource",
    "AsyncChannelsResource",
    "AsyncEndpointsResource",
    "AsyncTemplateVariablesResource",
    "AsyncMessagesResource",
    "AsyncContactsResource",
    "AsyncContactGroupsResource",
    "AsyncPipelinesResource",
    "AsyncLeadsResource",
    "AsyncBroadcastsResource",
    "AsyncFollowupsResource",
    "AsyncWebhooksResource",
]


class _AsyncResource:
    def __init__(self, http: AsyncApiClient) -> None:
        self._http = http


class AsyncMeResource(_AsyncResource):
    """``client.me`` — scope ``account:read``."""

    async def get(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetMeData]:
        return await self._http.request("GET", "/me", options=options)


class AsyncChannelsResource(_AsyncResource):
    """``client.channels`` — scope ``channels:read``."""

    async def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListChannelsData]:
        return await self._http.request("GET", "/channels", options=options)


class AsyncEndpointsResource(_AsyncResource):
    """``client.endpoints`` — sending endpoints (one per number/page/account). Scope ``channels:read``."""

    async def list(self, params: Optional[_t.ListEndpointsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListEndpointsItem]:
        return await self._http.request_page("/endpoints", _query(params, kwargs), options)

    async def get(self, endpoint_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetEndpointData]:
        return await self._http.request("GET", _path("endpoints", endpoint_id), options=options)

    async def templates(self, endpoint_id: str, params: Optional[_t.ListEndpointTemplatesQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListEndpointTemplatesItem]:
        """Approved WhatsApp templates of an official endpoint, with the variables to map."""
        return await self._http.request_page(f"{_path('endpoints', endpoint_id)}/templates", _query(params, kwargs), options)


class AsyncTemplateVariablesResource(_AsyncResource):
    """``client.template_variables`` — global placeholders such as ``{{full_name}}``. Scope ``channels:read``."""

    async def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListTemplateVariablesData]:
        return await self._http.request("GET", "/template-variables", options=options)


class AsyncMessagesResource(_AsyncResource):
    """``client.messages`` — omnichannel sending. Scopes ``messages:read`` / ``messages:write``."""

    async def send_options(self, contact: Optional[_t.GetMessageSendOptionsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> ApiResponse[_t.GetMessageSendOptionsData]:
        """Endpoints that can reach this contact.

        Exactly one of ``contact_id``, ``phone_number``, ``channel`` + ``external_id``, or ``email`` (+ ``endpoint_id``).
        """
        return await self._http.request("GET", "/messages/send-options", _query(contact, kwargs), options=options)

    async def send(self, message: _t.SendMessageBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.SendMessageData]:
        """202. A message the channel rejected is still 202 with ``status = failed`` and ``error``."""
        return await self._http.request("POST", "/messages", None, message, idempotency_key, options=options)

    async def get(self, message_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetMessageData]:
        return await self._http.request("GET", _path("messages", message_id), options=options)


class AsyncContactsResource(_AsyncResource):
    """``client.contacts`` — scopes ``contacts:read`` / ``contacts:write``."""

    async def list(self, params: Optional[_t.ListContactsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListContactsItem]:
        return await self._http.request_page("/contacts", _query(params, kwargs), options)

    async def lookup(self, by: Optional[_t.LookupContactQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> ApiResponse[_t.LookupContactData]:
        """Exactly one of ``phone_number``, ``email``, or ``channel`` + ``external_id`` (+ ``endpoint_id``).

        Not found → :class:`~plasgos_crm.errors.NotFoundError`.
        """
        return await self._http.request("GET", "/contacts/lookup", _query(by, kwargs), options=options)

    async def create(self, contact: _t.CreateContactBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateContactData]:
        """Existing phone/email → ``ConflictError`` ``identity_taken`` (``details["contact_id"]``)."""
        return await self._http.request("POST", "/contacts", None, contact, idempotency_key, options=options)

    async def upsert(self, contact: _t.UpsertContactBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UpsertContactData]:
        """201 created / 200 updated (``meta["created"]``). Tags and group ids are ADDED; may include ``match_on``."""
        return await self._http.request("POST", "/contacts/upsert", None, contact, idempotency_key, options=options)

    async def get(self, contact_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetContactData]:
        """A merged (source) contact id returns the target contact with ``meta["redirected_from"]``."""
        return await self._http.request("GET", _path("contacts", contact_id), options=options)

    async def update(self, contact_id: str, changes: _t.UpdateContactBody, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UpdateContactData]:
        """``tags`` / ``group_ids`` take ``{"add": [...], "remove": [...]}``."""
        return await self._http.request("PATCH", _path("contacts", contact_id), None, changes, options=options)


class AsyncContactGroupsResource(_AsyncResource):
    """``client.contact_groups`` — broadcast audiences. Scope ``contacts:read``."""

    async def list(self, params: Optional[_t.ListContactGroupsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListContactGroupsItem]:
        return await self._http.request_page("/contact-groups", _query(params, kwargs), options)


class AsyncPipelinesResource(_AsyncResource):
    """``client.pipelines`` — lead pipelines with their stages. Scope ``leads:read``."""

    async def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListPipelinesData]:
        return await self._http.request("GET", "/pipelines", options=options)


class AsyncLeadsResource(_AsyncResource):
    """``client.leads`` — scopes ``leads:read`` / ``leads:write``."""

    async def create(self, lead: _t.CreateLeadBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateLeadData]:
        """201 new lead; 200 with ``meta["created"] is False`` when the contact already has an active lead."""
        return await self._http.request("POST", "/leads", None, lead, idempotency_key, options=options)

    async def get(self, lead_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetLeadData]:
        return await self._http.request("GET", _path("leads", lead_id), options=options)


class AsyncBroadcastsResource(_AsyncResource):
    """``client.broadcasts`` — official WhatsApp template broadcasts. Scopes ``broadcasts:read`` / ``broadcasts:write``."""

    async def list(self, params: Optional[_t.ListBroadcastsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListBroadcastsItem]:
        return await self._http.request_page("/broadcasts", _query(params, kwargs), options)

    async def create(self, broadcast: _t.CreateBroadcastBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateBroadcastData]:
        """202. Rejected recipients do not fail the request: see ``meta["rejected_recipients"]``."""
        return await self._http.request("POST", "/broadcasts", None, broadcast, idempotency_key, options=options)

    async def get(self, broadcast_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetBroadcastData]:
        return await self._http.request("GET", _path("broadcasts", broadcast_id), options=options)

    async def recipients(self, broadcast_id: str, params: Optional[_t.ListBroadcastRecipientsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListBroadcastRecipientsItem]:
        return await self._http.request_page(f"{_path('broadcasts', broadcast_id)}/recipients", _query(params, kwargs), options)

    async def cancel(self, broadcast_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CancelBroadcastData]:
        """Completed/cancelled → ``ConflictError`` ``broadcast_not_cancellable``."""
        return await self._http.request("POST", f"{_path('broadcasts', broadcast_id)}/cancel", None, {}, options=options)


class AsyncFollowupsResource(_AsyncResource):
    """``client.followups`` — enrol contacts into sequences designed in the dashboard.

    Scopes ``followups:read`` / ``followups:write``.
    """

    async def list(self, params: Optional[_t.ListFollowupsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListFollowupsItem]:
        return await self._http.request_page("/followups", _query(params, kwargs), options)

    async def get(self, followup_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.GetFollowupData]:
        return await self._http.request("GET", _path("followups", followup_id), options=options)

    async def enrollments(self, followup_id: str, params: Optional[_t.ListFollowupEnrollmentsQuery] = None, *, options: Optional[RequestOptions] = None, **kwargs: QueryValue) -> AsyncPage[_t.ListFollowupEnrollmentsItem]:
        return await self._http.request_page(f"{_path('followups', followup_id)}/enrollments", _query(params, kwargs), options)

    async def enroll(self, followup_id: str, enrollment: _t.EnrollFollowupBody, idempotency_key: Optional[str] = None, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.EnrollFollowupData]:
        """201 enrolled; 200 with ``meta["created"] is False`` when still actively enrolled."""
        return await self._http.request(
            "POST", f"{_path('followups', followup_id)}/enrollments", None, enrollment, idempotency_key, options=options
        )

    async def unenroll(self, followup_id: str, contact_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UnenrollFollowupData]:
        """Stops the sequence for one contact (idempotent). Never enrolled → ``NotFoundError`` ``enrollment_not_found``."""
        return await self._http.request("DELETE", f"{_path('followups', followup_id)}/enrollments/{segment(contact_id)}", options=options)


class AsyncWebhooksResource(_AsyncResource):
    """``client.webhooks`` — subscriptions for this tenant. Scope ``webhooks:manage``."""

    async def list(self, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.ListWebhooksData]:
        return await self._http.request("GET", "/webhooks", options=options)

    async def create(self, subscription: _t.CreateWebhookBody, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.CreateWebhookData]:
        """``data["signing_secret"]`` is returned ONLY here — store it. https + public URL, max 5."""
        return await self._http.request("POST", "/webhooks", None, subscription, options=options)

    async def update(self, webhook_id: str, changes: _t.UpdateWebhookBody, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.UpdateWebhookData]:
        return await self._http.request("PATCH", _path("webhooks", webhook_id), None, changes, options=options)

    async def delete(self, webhook_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.DeleteWebhookData]:
        return await self._http.request("DELETE", _path("webhooks", webhook_id), options=options)

    async def test(self, webhook_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.TestWebhookData]:
        """Ask the CRM to deliver a ``webhook.test`` event now."""
        return await self._http.request("POST", f"{_path('webhooks', webhook_id)}/test", None, {}, options=options)

    async def rotate_secret(self, webhook_id: str, *, options: Optional[RequestOptions] = None) -> ApiResponse[_t.RotateWebhookSecretData]:
        """New signing secret; the previous one stays valid for 24 hours (two signatures meanwhile)."""
        return await self._http.request("POST", f"{_path('webhooks', webhook_id)}/rotate-secret", None, {}, options=options)
