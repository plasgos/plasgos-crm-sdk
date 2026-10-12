// ——— API Integration v3 (SDK 2.x) ———
export { Client, type ClientV3Options } from "./v3/client.js";
export { ApiResponse, Page, type Pagination, type RateLimit } from "./v3/response.js";
export {
  Content,
  TemplateContent,
  type MediaType,
  type TextContent,
  type MediaContent,
  type TemplateContentShape,
  type TemplateVariableValue,
  type TemplateLocation,
} from "./v3/content.js";
export { signV3, signatureHeadersV3, stringToSign, type V3SignatureHeaders } from "./v3/signature.js";
export { buildQuery, type Method, type Query } from "./v3/api-client.js";
export { Webhook, WebhookEventType, type WebhookEventV3, type WebhookEventTypeName } from "./v3/webhook.js";
export { SDK_VERSION } from "./version.js";

// ——— Legacy /api/v1 (deprecated, removed in 3.0) ———
export { LegacyClient, PlasgosCrmClient } from "./client.js";
export { PlasgosCrmCredentialsClient } from "./credentials-client.js";

export {
  computeSignature,
  currentTimestamp,
  signRequest,
  type SignatureHeaders,
} from "./signature.js";

export {
  BASE_URLS,
  resolveBaseUrl,
  type Account,
  type ApiCredential,
  type BaseUrlName,
  type Channel,
  type ClientOptions,
  type ContentType,
  type CredentialsClientOptions,
  type Envelope,
  type ListBroadcastsParams,
  type ListContactsParams,
  type Message,
  type MessageContentInput,
  type OfficialBroadcastInput,
  type OfficialBroadcastRecipientInput,
  type OfficialSendTemplateInput,
  type OfficialTemplateAttachmentInput,
  type SendMessageInput,
  type SubscribeWebhookInput,
  type TemplateVariable,
} from "./types.js";

export type { SendMessageResult } from "./resources/messages.js";
export type { TemplateCategory } from "./resources/official/templates.js";
export type { SendOtpInput, SendOtpResult } from "./resources/official/shared-waba.js";

export {
  PlasgosApiError,
  AuthenticationError,
  PermissionError,
  PlanError,
  NotFoundError,
  SignatureExpiredError,
  ConflictError,
  ValidationError,
  BadRequestError,
  RateLimitError,
  ServerError,
  ChannelError,
  ServiceUnavailableError,
  TransportError,
  WebhookVerificationError,
  errorFromV3,
  type ValidationIssue,
  type FieldError,
} from "./errors.js";

export {
  verifyWebhook,
  plasgosWebhook,
  verifyLegacyWebhook,
  legacyWebhookMiddleware,
  WebhookEventName,
  type WebhookEvent,
  type WebhookEventBase,
  type MessageReceivedEvent,
  type MessageStatusesEvent,
  type VerifyWebhookOptions,
} from "./webhooks/index.js";
