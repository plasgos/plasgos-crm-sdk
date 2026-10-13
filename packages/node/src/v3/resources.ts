import { segment, type ApiClient, type Query } from "./api-client.js";
import type { ApiResponse, Page } from "./response.js";

/**
 * API v3 resources (docs/SDK_SPEC.md §3). Inputs and outputs use the wire format (snake_case);
 * the OpenAPI schemas (`openapi/openapi-v3.json`) describe them.
 */
type Body = Record<string, unknown> | object;
const path = (...parts: string[]): string => parts.map((part, i) => (i % 2 === 1 ? segment(part) : part)).join("/");

abstract class Resource {
  constructor(protected readonly http: ApiClient) {}
}

/** `client.me` — scope `account:read`. */
export class MeResource extends Resource {
  get(): Promise<ApiResponse> {
    return this.http.request("GET", "/me");
  }
}

/** `client.channels` — scope `channels:read`. */
export class ChannelsResource extends Resource {
  list(): Promise<ApiResponse> {
    return this.http.request("GET", "/channels");
  }
}

/** `client.endpoints` — sending endpoints (one per number/page/account). Scope `channels:read`. */
export class EndpointsResource extends Resource {
  list(params: Query = {}): Promise<Page> {
    return this.http.requestPage("/endpoints", params);
  }

  get(endpointId: string): Promise<ApiResponse> {
    return this.http.request("GET", path("endpoints", endpointId));
  }

  /** Approved WhatsApp templates of an official endpoint, with the variables to map. */
  templates(endpointId: string, params: Query = {}): Promise<Page> {
    return this.http.requestPage(`${path("endpoints", endpointId)}/templates`, params);
  }
}

/** `client.templateVariables` — global placeholders such as `{{full_name}}`. Scope `channels:read`. */
export class TemplateVariablesResource extends Resource {
  list(): Promise<ApiResponse> {
    return this.http.request("GET", "/template-variables");
  }
}

/** `client.messages` — omnichannel sending. Scopes `messages:read` / `messages:write`. */
export class MessagesResource extends Resource {
  /** Endpoints that can reach this contact; exactly one of contact_id, phone_number, channel + external_id, or email (+ endpoint_id). */
  sendOptions(contact: Query): Promise<ApiResponse> {
    return this.http.request("GET", "/messages/send-options", contact);
  }

  /** 202. A message the channel rejected is still 202 with `status = failed` and `error`. */
  send(input: Body, idempotencyKey?: string): Promise<ApiResponse> {
    return this.http.request("POST", "/messages", {}, input, idempotencyKey);
  }

  get(messageId: string): Promise<ApiResponse> {
    return this.http.request("GET", path("messages", messageId));
  }
}

/** `client.contacts` — scopes `contacts:read` / `contacts:write`. */
export class ContactsResource extends Resource {
  list(params: Query = {}): Promise<Page> {
    return this.http.requestPage("/contacts", params);
  }

  /** Exactly one of phone_number, email, or channel + external_id (+ endpoint_id). Not found → NotFoundError. */
  lookup(by: Query): Promise<ApiResponse> {
    return this.http.request("GET", "/contacts/lookup", by);
  }

  /** Existing phone/email → ConflictError `identity_taken` (`details.contact_id`). */
  create(contact: Body, idempotencyKey?: string): Promise<ApiResponse> {
    return this.http.request("POST", "/contacts", {}, contact, idempotencyKey);
  }

  /** 201 created / 200 updated (`meta.created`). Tags and group ids are ADDED; may include `match_on`. */
  upsert(contact: Body, idempotencyKey?: string): Promise<ApiResponse> {
    return this.http.request("POST", "/contacts/upsert", {}, contact, idempotencyKey);
  }

  /** A merged (source) contact id returns the target contact with `meta.redirected_from`. */
  get(contactId: string): Promise<ApiResponse> {
    return this.http.request("GET", path("contacts", contactId));
  }

  /** `tags` / `group_ids` take `{ add: [], remove: [] }`. */
  update(contactId: string, changes: Body): Promise<ApiResponse> {
    return this.http.request("PATCH", path("contacts", contactId), {}, changes);
  }
}

/** `client.contactGroups` — broadcast audiences. Scope `contacts:read`. */
export class ContactGroupsResource extends Resource {
  list(params: Query = {}): Promise<Page> {
    return this.http.requestPage("/contact-groups", params);
  }
}

/** `client.pipelines` — lead pipelines with their stages. Scope `leads:read`. */
export class PipelinesResource extends Resource {
  list(): Promise<ApiResponse> {
    return this.http.request("GET", "/pipelines");
  }
}

/** `client.leads` — scopes `leads:read` / `leads:write`. */
export class LeadsResource extends Resource {
  /** 201 new lead; 200 with `meta.created === false` when the contact already has an active lead. */
  create(lead: Body, idempotencyKey?: string): Promise<ApiResponse> {
    return this.http.request("POST", "/leads", {}, lead, idempotencyKey);
  }

  get(leadId: string): Promise<ApiResponse> {
    return this.http.request("GET", path("leads", leadId));
  }
}

/** `client.broadcasts` — official WhatsApp template broadcasts. Scopes `broadcasts:read` / `broadcasts:write`. */
export class BroadcastsResource extends Resource {
  list(params: Query = {}): Promise<Page> {
    return this.http.requestPage("/broadcasts", params);
  }

  /** 202. Rejected recipients do not fail the request: see `meta.rejected_recipients`. */
  create(broadcast: Body, idempotencyKey?: string): Promise<ApiResponse> {
    return this.http.request("POST", "/broadcasts", {}, broadcast, idempotencyKey);
  }

  get(broadcastId: string): Promise<ApiResponse> {
    return this.http.request("GET", path("broadcasts", broadcastId));
  }

  recipients(broadcastId: string, params: Query = {}): Promise<Page> {
    return this.http.requestPage(`${path("broadcasts", broadcastId)}/recipients`, params);
  }

  /** Completed/cancelled → ConflictError `broadcast_not_cancellable`. */
  cancel(broadcastId: string): Promise<ApiResponse> {
    return this.http.request("POST", `${path("broadcasts", broadcastId)}/cancel`, {}, {});
  }
}

/** `client.followups` — enrol contacts into sequences designed in the dashboard. Scopes `followups:read` / `followups:write`. */
export class FollowupsResource extends Resource {
  list(params: Query = {}): Promise<Page> {
    return this.http.requestPage("/followups", params);
  }

  get(followupId: string): Promise<ApiResponse> {
    return this.http.request("GET", path("followups", followupId));
  }

  enrollments(followupId: string, params: Query = {}): Promise<Page> {
    return this.http.requestPage(`${path("followups", followupId)}/enrollments`, params);
  }

  /** 201 enrolled; 200 with `meta.created === false` when still actively enrolled. */
  enroll(followupId: string, enrollment: Body, idempotencyKey?: string): Promise<ApiResponse> {
    return this.http.request("POST", `${path("followups", followupId)}/enrollments`, {}, enrollment, idempotencyKey);
  }

  /** Stops the sequence for one contact (idempotent). Never enrolled → NotFoundError `enrollment_not_found`. */
  unenroll(followupId: string, contactId: string): Promise<ApiResponse> {
    return this.http.request("DELETE", `${path("followups", followupId)}/enrollments/${segment(contactId)}`);
  }
}

/** `client.webhooks` — subscriptions for this tenant. Scope `webhooks:manage`. */
export class WebhooksResource extends Resource {
  list(): Promise<ApiResponse> {
    return this.http.request("GET", "/webhooks");
  }

  /** `data.signing_secret` is returned ONLY here — store it. https + public URL, max 5. */
  create(subscription: Body): Promise<ApiResponse> {
    return this.http.request("POST", "/webhooks", {}, subscription);
  }

  update(webhookId: string, changes: Body): Promise<ApiResponse> {
    return this.http.request("PATCH", path("webhooks", webhookId), {}, changes);
  }

  delete(webhookId: string): Promise<ApiResponse> {
    return this.http.request("DELETE", path("webhooks", webhookId));
  }

  /** Ask the CRM to deliver a `webhook.test` event now. */
  test(webhookId: string): Promise<ApiResponse> {
    return this.http.request("POST", `${path("webhooks", webhookId)}/test`, {}, {});
  }

  /** New signing secret; the previous one stays valid for 24 hours (two signatures meanwhile). */
  rotateSecret(webhookId: string): Promise<ApiResponse> {
    return this.http.request("POST", `${path("webhooks", webhookId)}/rotate-secret`, {}, {});
  }
}
