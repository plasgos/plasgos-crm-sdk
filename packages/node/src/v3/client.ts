import { ApiClient, type Method, type Query } from "./api-client.js";
import type { ApiResponse } from "./response.js";
import {
  BroadcastsResource,
  ChannelsResource,
  ContactGroupsResource,
  ContactsResource,
  EndpointsResource,
  FollowupsResource,
  LeadsResource,
  MeResource,
  MessagesResource,
  PipelinesResource,
  TemplateVariablesResource,
  WebhooksResource,
} from "./resources.js";
import { resolveBaseUrl, type BaseUrlName } from "../types.js";
import { SDK_VERSION } from "../version.js";

export interface ClientV3Options {
  /** `production` (default), `sandbox`, or an absolute URL (host only; `/api/v3` is appended). */
  baseUrl?: BaseUrlName | string;
  /** Per-request timeout in ms. Default 15 000. */
  timeout?: number;
  /** Automatic retries (docs/SDK_SPEC.md §6). Default 2. */
  maxRetries?: number;
  /** Longest `Retry-After` in seconds honoured automatically. Default 30. */
  maxRetryWait?: number;
  /** Sent as `User-Agent`; integrations append themselves. */
  userAgent?: string;
  /** Injectable fetch (defaults to the global one). */
  fetch?: typeof fetch;
  /** Test hook: wait `ms` milliseconds between retries. */
  sleep?: (ms: number) => Promise<void>;
  /** Test hook: current Unix time in seconds (signing timestamp). */
  clock?: () => number;
}

/**
 * Plasgos CRM API Integration v3 client (`/api/v3/*`).
 *
 * ```ts
 * const client = new Client(apiKey, secretKey, { baseUrl: "sandbox" });
 * const res = await client.messages.send(
 *   { endpoint_id: "6281100000001", contact: { phone_number: "081234567890" }, content: Content.text("Halo") },
 *   "order-1001-shipped",
 * );
 * ```
 */
export class Client {
  readonly me: MeResource;
  readonly channels: ChannelsResource;
  readonly endpoints: EndpointsResource;
  readonly templateVariables: TemplateVariablesResource;
  readonly messages: MessagesResource;
  readonly contacts: ContactsResource;
  readonly contactGroups: ContactGroupsResource;
  readonly pipelines: PipelinesResource;
  readonly leads: LeadsResource;
  readonly broadcasts: BroadcastsResource;
  readonly followups: FollowupsResource;
  readonly webhooks: WebhooksResource;

  private readonly http: ApiClient;

  constructor(apiKey: string, secretKey: string, options: ClientV3Options = {}) {
    if (!apiKey) throw new TypeError("Client: apiKey is required");
    if (!secretKey) throw new TypeError("Client: secretKey is required");

    this.http = new ApiClient({
      baseUrl: resolveBaseUrl(options.baseUrl),
      apiKey,
      secretKey,
      timeout: options.timeout ?? 15_000,
      maxRetries: options.maxRetries ?? 2,
      maxRetryWait: options.maxRetryWait ?? 30,
      userAgent: options.userAgent ?? `plasgos-crm-sdk-node/${SDK_VERSION} node/${process.versions.node}`,
      fetchImpl: options.fetch ?? globalThis.fetch,
      sleep: options.sleep ?? ((ms) => new Promise((resolve) => setTimeout(resolve, ms))),
      clock: options.clock ?? (() => Math.floor(Date.now() / 1000)),
    });

    this.me = new MeResource(this.http);
    this.channels = new ChannelsResource(this.http);
    this.endpoints = new EndpointsResource(this.http);
    this.templateVariables = new TemplateVariablesResource(this.http);
    this.messages = new MessagesResource(this.http);
    this.contacts = new ContactsResource(this.http);
    this.contactGroups = new ContactGroupsResource(this.http);
    this.pipelines = new PipelinesResource(this.http);
    this.leads = new LeadsResource(this.http);
    this.broadcasts = new BroadcastsResource(this.http);
    this.followups = new FollowupsResource(this.http);
    this.webhooks = new WebhooksResource(this.http);
  }

  /** Escape hatch for endpoints without a resource method yet. */
  request<T = any>(method: Method, path: string, query: Query = {}, body?: unknown, idempotencyKey?: string): Promise<ApiResponse<T>> {
    return this.http.request<T>(method, path, query, body, idempotencyKey);
  }
}
