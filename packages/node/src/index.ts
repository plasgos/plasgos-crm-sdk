export { PlasgosCrmClient } from "./client.js";
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
  TransportError,
  WebhookVerificationError,
  type ValidationIssue,
} from "./errors.js";

export {
  verifyWebhook,
  plasgosWebhook,
  WebhookEventName,
  type WebhookEvent,
  type WebhookEventBase,
  type MessageReceivedEvent,
  type MessageStatusesEvent,
  type VerifyWebhookOptions,
} from "./webhooks/index.js";
