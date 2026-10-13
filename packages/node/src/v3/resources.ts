import { segment, type ApiClient, type KeyOrOptions, type RequestOptions } from "./api-client.js";
import type { ApiResponse, Page } from "./response.js";
import type { Operations } from "./types.gen.js";

/**
 * API v3 resources (docs/SDK_SPEC.md §3). Inputs and outputs use the wire format (snake_case);
 * the OpenAPI schemas (`openapi/openapi-v3.json`) describe them.
 */
type Op = keyof Operations;
/** Respons satu operasi: `data` & `meta` bertipe dari OpenAPI (`Types.Operations`). */
type Result<K extends Op> = Promise<ApiResponse<Operations[K]["data"], Operations[K]["meta"]>>;
/** Satu halaman daftar; item bertipe dari OpenAPI. */
type Listing<K extends Op> = Promise<Page<Operations[K] extends { item: infer I } ? I : unknown>>;
type QueryOf<K extends Op> = Operations[K]["query"];
type BodyOf<K extends Op> = Operations[K]["body"];
const path = (...parts: string[]): string => parts.map((part, i) => (i % 2 === 1 ? segment(part) : part)).join("/");

abstract class Resource {
  constructor(protected readonly http: ApiClient) {}
}

/** `client.me` — scope `account:read`. */
export class MeResource extends Resource {
  get(options?: RequestOptions): Result<"getMe"> {
    return this.http.request("GET", "/me", {}, undefined, options);
  }
}

/** `client.channels` — scope `channels:read`. */
export class ChannelsResource extends Resource {
  list(options?: RequestOptions): Result<"listChannels"> {
    return this.http.request("GET", "/channels", {}, undefined, options);
  }
}

/** `client.endpoints` — sending endpoints (one per number/page/account). Scope `channels:read`. */
export class EndpointsResource extends Resource {
  list(params: QueryOf<"listEndpoints"> = {}, options?: RequestOptions): Listing<"listEndpoints"> {
    return this.http.requestPage("/endpoints", params, options);
  }

  get(endpointId: string, options?: RequestOptions): Result<"getEndpoint"> {
    return this.http.request("GET", path("endpoints", endpointId), {}, undefined, options);
  }

  /** Approved WhatsApp templates of an official endpoint, with the variables to map. */
  templates(endpointId: string, params: QueryOf<"listEndpointTemplates"> = {}, options?: RequestOptions): Listing<"listEndpointTemplates"> {
    return this.http.requestPage(`${path("endpoints", endpointId)}/templates`, params, options);
  }
}

/** `client.templateVariables` — global placeholders such as `{{full_name}}`. Scope `channels:read`. */
export class TemplateVariablesResource extends Resource {
  list(options?: RequestOptions): Result<"listTemplateVariables"> {
    return this.http.request("GET", "/template-variables", {}, undefined, options);
  }
}

/** `client.messages` — omnichannel sending. Scopes `messages:read` / `messages:write`. */
export class MessagesResource extends Resource {
  /** Endpoints that can reach this contact; exactly one of contact_id, phone_number, channel + external_id, or email (+ endpoint_id). */
  sendOptions(contact: QueryOf<"getMessageSendOptions">, options?: RequestOptions): Result<"getMessageSendOptions"> {
    return this.http.request("GET", "/messages/send-options", contact, undefined, options);
  }

  /** 202. A message the channel rejected is still 202 with `status = failed` and `error`. */
  send(input: BodyOf<"sendMessage">, options?: KeyOrOptions): Result<"sendMessage"> {
    return this.http.request("POST", "/messages", {}, input, options);
  }

  get(messageId: string, options?: RequestOptions): Result<"getMessage"> {
    return this.http.request("GET", path("messages", messageId), {}, undefined, options);
  }
}

/** `client.contacts` — scopes `contacts:read` / `contacts:write`. */
export class ContactsResource extends Resource {
  list(params: QueryOf<"listContacts"> = {}, options?: RequestOptions): Listing<"listContacts"> {
    return this.http.requestPage("/contacts", params, options);
  }

  /** Exactly one of phone_number, email, or channel + external_id (+ endpoint_id). Not found → NotFoundError. */
  lookup(by: QueryOf<"lookupContact">, options?: RequestOptions): Result<"lookupContact"> {
    return this.http.request("GET", "/contacts/lookup", by, undefined, options);
  }

  /** Existing phone/email → ConflictError `identity_taken` (`details.contact_id`). */
  create(contact: BodyOf<"createContact">, options?: KeyOrOptions): Result<"createContact"> {
    return this.http.request("POST", "/contacts", {}, contact, options);
  }

  /** 201 created / 200 updated (`meta.created`). Tags and group ids are ADDED; may include `match_on`. */
  upsert(contact: BodyOf<"upsertContact">, options?: KeyOrOptions): Result<"upsertContact"> {
    return this.http.request("POST", "/contacts/upsert", {}, contact, options);
  }

  /** A merged (source) contact id returns the target contact with `meta.redirected_from`. */
  get(contactId: string, options?: RequestOptions): Result<"getContact"> {
    return this.http.request("GET", path("contacts", contactId), {}, undefined, options);
  }

  /** `tags` / `group_ids` take `{ add: [], remove: [] }`. */
  update(contactId: string, changes: BodyOf<"updateContact">, options?: RequestOptions): Result<"updateContact"> {
    return this.http.request("PATCH", path("contacts", contactId), {}, changes, options);
  }
}

/** `client.contactGroups` — broadcast audiences. Scope `contacts:read`. */
export class ContactGroupsResource extends Resource {
  list(params: QueryOf<"listContactGroups"> = {}, options?: RequestOptions): Listing<"listContactGroups"> {
    return this.http.requestPage("/contact-groups", params, options);
  }
}

/** `client.pipelines` — lead pipelines with their stages. Scope `leads:read`. */
export class PipelinesResource extends Resource {
  list(options?: RequestOptions): Result<"listPipelines"> {
    return this.http.request("GET", "/pipelines", {}, undefined, options);
  }
}

/** `client.leads` — scopes `leads:read` / `leads:write`. */
export class LeadsResource extends Resource {
  /** 201 new lead; 200 with `meta.created === false` when the contact already has an active lead. */
  create(lead: BodyOf<"createLead">, options?: KeyOrOptions): Result<"createLead"> {
    return this.http.request("POST", "/leads", {}, lead, options);
  }

  get(leadId: string, options?: RequestOptions): Result<"getLead"> {
    return this.http.request("GET", path("leads", leadId), {}, undefined, options);
  }
}

/** `client.broadcasts` — official WhatsApp template broadcasts. Scopes `broadcasts:read` / `broadcasts:write`. */
export class BroadcastsResource extends Resource {
  list(params: QueryOf<"listBroadcasts"> = {}, options?: RequestOptions): Listing<"listBroadcasts"> {
    return this.http.requestPage("/broadcasts", params, options);
  }

  /** 202. Rejected recipients do not fail the request: see `meta.rejected_recipients`. */
  create(broadcast: BodyOf<"createBroadcast">, options?: KeyOrOptions): Result<"createBroadcast"> {
    return this.http.request("POST", "/broadcasts", {}, broadcast, options);
  }

  get(broadcastId: string, options?: RequestOptions): Result<"getBroadcast"> {
    return this.http.request("GET", path("broadcasts", broadcastId), {}, undefined, options);
  }

  recipients(broadcastId: string, params: QueryOf<"listBroadcastRecipients"> = {}, options?: RequestOptions): Listing<"listBroadcastRecipients"> {
    return this.http.requestPage(`${path("broadcasts", broadcastId)}/recipients`, params, options);
  }

  /** Completed/cancelled → ConflictError `broadcast_not_cancellable`. */
  cancel(broadcastId: string, options?: RequestOptions): Result<"cancelBroadcast"> {
    return this.http.request("POST", `${path("broadcasts", broadcastId)}/cancel`, {}, {}, options);
  }
}

/** `client.followups` — enrol contacts into sequences designed in the dashboard. Scopes `followups:read` / `followups:write`. */
export class FollowupsResource extends Resource {
  list(params: QueryOf<"listFollowups"> = {}, options?: RequestOptions): Listing<"listFollowups"> {
    return this.http.requestPage("/followups", params, options);
  }

  get(followupId: string, options?: RequestOptions): Result<"getFollowup"> {
    return this.http.request("GET", path("followups", followupId), {}, undefined, options);
  }

  enrollments(followupId: string, params: QueryOf<"listFollowupEnrollments"> = {}, options?: RequestOptions): Listing<"listFollowupEnrollments"> {
    return this.http.requestPage(`${path("followups", followupId)}/enrollments`, params, options);
  }

  /** 201 enrolled; 200 with `meta.created === false` when still actively enrolled. */
  enroll(followupId: string, enrollment: BodyOf<"enrollFollowup">, options?: KeyOrOptions): Result<"enrollFollowup"> {
    return this.http.request("POST", `${path("followups", followupId)}/enrollments`, {}, enrollment, options);
  }

  /** Stops the sequence for one contact (idempotent). Never enrolled → NotFoundError `enrollment_not_found`. */
  unenroll(followupId: string, contactId: string, options?: RequestOptions): Result<"unenrollFollowup"> {
    return this.http.request("DELETE", `${path("followups", followupId)}/enrollments/${segment(contactId)}`, {}, undefined, options);
  }
}

/** `client.webhooks` — subscriptions for this tenant. Scope `webhooks:manage`. */
export class WebhooksResource extends Resource {
  list(options?: RequestOptions): Result<"listWebhooks"> {
    return this.http.request("GET", "/webhooks", {}, undefined, options);
  }

  /** `data.signing_secret` is returned ONLY here — store it. https + public URL, max 5. */
  create(subscription: BodyOf<"createWebhook">, options?: RequestOptions): Result<"createWebhook"> {
    return this.http.request("POST", "/webhooks", {}, subscription, options);
  }

  update(webhookId: string, changes: BodyOf<"updateWebhook">, options?: RequestOptions): Result<"updateWebhook"> {
    return this.http.request("PATCH", path("webhooks", webhookId), {}, changes, options);
  }

  delete(webhookId: string, options?: RequestOptions): Result<"deleteWebhook"> {
    return this.http.request("DELETE", path("webhooks", webhookId), {}, undefined, options);
  }

  /** Ask the CRM to deliver a `webhook.test` event now. */
  test(webhookId: string, options?: RequestOptions): Result<"testWebhook"> {
    return this.http.request("POST", `${path("webhooks", webhookId)}/test`, {}, {}, options);
  }

  /** New signing secret; the previous one stays valid for 24 hours (two signatures meanwhile). */
  rotateSecret(webhookId: string, options?: RequestOptions): Result<"rotateWebhookSecret"> {
    return this.http.request("POST", `${path("webhooks", webhookId)}/rotate-secret`, {}, {}, options);
  }
}
