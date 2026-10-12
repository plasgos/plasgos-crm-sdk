import { HttpClient } from "./http.js";
import { Resource, compact } from "./resources/base.js";
import {
  resolveBaseUrl,
  type ApiCredential,
  type CredentialsClientOptions,
  type Envelope,
  type Message,
  type SubscribeWebhookInput,
} from "./types.js";

import { SDK_VERSION } from "./version.js";

class CredentialsResource extends Resource {
  /** Current API credential for the logged-in user (`data` is `null` if none). */
  get(): Promise<Envelope<ApiCredential | null>> {
    return this.http.request<ApiCredential | null>({ method: "GET", path: "/v2/integration" });
  }

  /** Request API access — creates a pending credential awaiting admin approval. */
  request(): Promise<Envelope<ApiCredential>> {
    return this.http.request<ApiCredential>({ method: "POST", path: "/v2/integration" });
  }

  /** Regenerate `api_key` + `secret_key` (only allowed when status is `active`). */
  regenerate(): Promise<Envelope<ApiCredential>> {
    return this.http.request<ApiCredential>({
      method: "POST",
      path: "/v2/integration/regenerate",
    });
  }

  /** Paginated history of messages sent through the API. */
  messages(params: { page?: number; limit?: number } = {}): Promise<Envelope<Message[]>> {
    return this.http.request<Message[]>({
      method: "GET",
      path: "/v2/integration/messages",
      query: compact({ page: params.page, limit: params.limit }),
    });
  }

  /**
   * Register (and server-side verify) the integrator webhook endpoint.
   * The server performs a verification GET before storing the URL.
   */
  subscribeWebhook(input: SubscribeWebhookInput): Promise<Envelope<Record<string, unknown>>> {
    return this.http.request({
      method: "POST",
      path: "/v2/integration/webhook",
      body: compact({
        webhook_url: input.webhookUrl,
        verify_token: input.verifyToken,
      }),
    });
  }
}

/**
 * Client for the credential-management endpoints (`/v2/integration/*`).
 *
 * These use the CRM **user session** auth, not the signed API auth:
 * `Authorization: Bearer <accessToken>` + `fingerprint: <fingerprint>`.
 * Pass the exact token string your CRM session issued — the SDK does not
 * encrypt or refresh it.
 */
export class PlasgosCrmCredentialsClient {
  readonly credentials: CredentialsResource;
  private readonly http: HttpClient;

  constructor(
    private readonly accessToken: string,
    private readonly fingerprint: string,
    options: CredentialsClientOptions = {},
  ) {
    if (!accessToken) throw new TypeError("CredentialsClient: accessToken is required");
    if (!fingerprint) throw new TypeError("CredentialsClient: fingerprint is required");

    this.http = new HttpClient({
      baseUrl: resolveBaseUrl(options.baseUrl),
      timeout: options.timeout ?? 15_000,
      maxRetries: options.maxRetries ?? 2,
      userAgent: options.userAgent ?? `plasgos-crm-sdk-node/${SDK_VERSION}`,
      fetchImpl: options.fetch ?? globalThis.fetch,
      authHeaders: () => ({
        Authorization: `Bearer ${this.accessToken}`,
        fingerprint: this.fingerprint,
      }),
    });

    this.credentials = new CredentialsResource(this.http);
  }
}
