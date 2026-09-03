import { HttpClient } from "./http.js";
import { signRequest, currentTimestamp } from "./signature.js";
import { AccountsResource } from "./resources/accounts.js";
import { MessagesResource } from "./resources/messages.js";
import { ReferencesResource } from "./resources/references.js";
import { OfficialNamespace } from "./resources/official/index.js";
import { resolveBaseUrl, type ClientOptions } from "./types.js";

const SDK_VERSION = "1.0.4";
const DEFAULT_TIMEOUT = 15_000;
const DEFAULT_RETRIES = 2;
const DEFAULT_SIGNATURE_TTL = 300;

/**
 * Signed API client for `/api/v1/*`.
 *
 * ```ts
 * const client = new PlasgosCrmClient(apiKey, secretKey, { baseUrl: "sandbox" });
 * const res = await client.messages.send({
 *   channel: "whatsapp",
 *   account: { accountId },
 *   receiver: { phoneNumber: "628123456789", name: "John" },
 *   content: { type: "text", data: { text: "Hi" } },
 * });
 * ```
 */
export class PlasgosCrmClient {
  readonly messages: MessagesResource;
  readonly accounts: AccountsResource;
  readonly references: ReferencesResource;
  readonly official: OfficialNamespace;

  /** Server-side signature TTL in seconds (informational). */
  readonly signatureTtl: number;

  private readonly http: HttpClient;

  constructor(
    private readonly apiKey: string,
    private readonly secretKey: string,
    options: ClientOptions = {},
  ) {
    if (!apiKey) throw new TypeError("PlasgosCrmClient: apiKey is required");
    if (!secretKey) throw new TypeError("PlasgosCrmClient: secretKey is required");

    // Reserved for future local-clock-skew diagnostics; the server enforces it.
    this.signatureTtl = options.signatureTtl ?? DEFAULT_SIGNATURE_TTL;

    this.http = new HttpClient({
      baseUrl: resolveBaseUrl(options.baseUrl),
      timeout: options.timeout ?? DEFAULT_TIMEOUT,
      maxRetries: options.maxRetries ?? DEFAULT_RETRIES,
      userAgent: options.userAgent ?? `plasgos-crm-sdk-node/${SDK_VERSION}`,
      fetchImpl: options.fetch ?? globalThis.fetch,
      authHeaders: () =>
        signRequest(this.apiKey, this.secretKey, currentTimestamp()),
    });

    this.messages = new MessagesResource(this.http);
    this.accounts = new AccountsResource(this.http);
    this.references = new ReferencesResource(this.http);
    this.official = new OfficialNamespace(this.http);
  }

  /** The three auth headers the SDK would send for a request right now. */
  signatureHeaders(timestampSeconds?: number): Record<string, string> {
    return signRequest(this.apiKey, this.secretKey, timestampSeconds ?? currentTimestamp());
  }
}
