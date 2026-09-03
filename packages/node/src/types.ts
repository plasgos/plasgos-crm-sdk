/** Named environments; also accepts any absolute URL string. */
export type BaseUrlName = "production" | "sandbox";

export const BASE_URLS: Record<BaseUrlName, string> = {
  production: "https://wa-client.plasgos.co.id",
  sandbox: "https://api-crm.sandbox.plasgos.co.id",
};

export function resolveBaseUrl(input: BaseUrlName | string | undefined): string {
  if (!input) return BASE_URLS.production;
  if (input in BASE_URLS) return BASE_URLS[input as BaseUrlName];
  return input.replace(/\/+$/, "");
}

/** Normalised response wrapper returned by every successful SDK call. */
export interface Envelope<T = unknown> {
  /** Status the body reported (number or bool), else the real HTTP status. */
  status: number | boolean;
  message: string | null;
  data: T;
  metadata: Record<string, unknown>;
  /** The untouched parsed body. */
  raw: unknown;
  /** The real HTTP status code. */
  httpStatus: number;
}

export interface ClientOptions {
  /** `"production"` (default), `"sandbox"`, or an absolute base URL. */
  baseUrl?: BaseUrlName | string;
  /** Per-request timeout in ms. Default 15000. */
  timeout?: number;
  /** Informational; warns on obvious local clock skew. Default 300. */
  signatureTtl?: number;
  /** Overrides the default `User-Agent`. */
  userAgent?: string;
  /** Retries for idempotent (GET) requests. Default 2. */
  maxRetries?: number;
  /** Inject a custom fetch implementation (tests, non-standard runtimes). */
  fetch?: typeof fetch;
}

export interface CredentialsClientOptions
  extends Omit<ClientOptions, "signatureTtl"> {}

// --- Domain models -------------------------------------------------------------

export type Channel = "whatsapp" | "telegram" | "messanger" | "instagram";
export type ContentType = "text" | "image" | "video" | "document";

export interface MessageContentInput {
  type: ContentType;
  data: {
    text?: string;
    url?: string;
    caption?: string;
    mimeType?: string;
    fileName?: string;
  };
}

export interface SendMessageInput {
  /** Client-generated, globally unique. Omit to let the SDK generate a UUID. */
  messageId?: string;
  channel: Channel;
  account: { accountId: string };
  receiver: { phoneNumber: string; name: string };
  content: MessageContentInput;
}

export interface Account {
  id: string;
  name: string | null;
  phone_number: string | null;
}

export interface Message {
  message_id: string;
  channel: string;
  account?: Account | string;
  account_id?: string;
  receiver: { phone_number: string; name: string; jid?: string };
  content: { type: string; data: Record<string, unknown> };
  sent: boolean;
  sent_at: number | string | null;
}

export interface TemplateVariable {
  label: string;
  value: string;
  example: string;
}

export interface OfficialTemplateAttachmentInput {
  type: "image" | "video" | "document";
  link: string;
  mimeType: string;
  fileName: string;
  originalName?: string;
  fileSize?: number;
}

export interface OfficialSendTemplateInput {
  phoneNumberId: string;
  to: string;
  template: {
    id: string;
    name: string;
    category: "TRANSACTIONAL" | "MARKETING" | "OTP" | "UTILITY" | "AUTHENTICATION";
    parameters?: {
      variables?: Array<{ type: "dynamic" | "custom"; name: string; value: string }>;
      attachment?: OfficialTemplateAttachmentInput;
    };
  };
}

export interface OfficialBroadcastRecipientInput {
  phoneNumber: string;
  attachment?: {
    type: "image" | "video" | "document";
    link: string;
    mimeType: string;
    fileName: string;
  };
}

export interface OfficialBroadcastInput {
  accountId: string;
  templateId: string;
  phoneNumberId: string;
  name: string;
  description?: string;
  /** `"YYYY-MM-DD HH:mm:ss"` */
  scheduledAt: string;
  requestId: string;
  variables?: Array<{ name: string; value: string }>;
  location?: Record<string, unknown>;
  recipients: OfficialBroadcastRecipientInput[];
}

export interface ListContactsParams {
  page?: number;
  limit?: number;
  search?: string;
}

export interface ListBroadcastsParams {
  page?: number;
  limit?: number;
  status?: string;
  search?: string;
}

export interface ApiCredential {
  api_key: string | null;
  secret_key: string | null;
  permissions: string[];
  status: "pending" | "active" | "revoked";
  approved_at: string | null;
  requested_at?: string | null;
  regenerated_at?: string | null;
}

export interface SubscribeWebhookInput {
  webhookUrl: string;
  verifyToken?: string;
}
