export interface ApiErrorFields {
  message: string;
  httpStatus: number;
  apiStatus?: number | string | null;
  errors?: ValidationIssue[];
  raw?: unknown;
  requestId?: string | null;
  cause?: unknown;
  /** v3: stable machine code, e.g. `requires_template`. Null for legacy responses. */
  errorCode?: string | null;
  /** v3: error family, e.g. `invalid_request`, `authentication_error`. */
  errorType?: string | null;
  /** v3: context for screens, e.g. the conflicting contact id. */
  details?: Record<string, unknown> | unknown[] | null;
  /** v3: per-field validation errors (dot-notation `field`). */
  fields?: FieldError[];
  /** v3: link to the error documentation. */
  docUrl?: string | null;
  /** Seconds from the `Retry-After` header. */
  retryAfter?: number | null;
}

/** One v3 validation problem. */
export interface FieldError {
  field: string;
  code: string;
  message: string;
}

export interface ValidationIssue {
  message: string;
  path?: Array<string | number>;
}

/** Base class for every error raised by the SDK. */
export class PlasgosApiError extends Error {
  readonly httpStatus: number;
  readonly apiStatus: number | string | null;
  readonly errors: ValidationIssue[];
  readonly raw: unknown;
  readonly requestId: string | null;
  readonly errorCode: string | null;
  readonly errorType: string | null;
  readonly details: Record<string, unknown> | unknown[] | null;
  readonly fields: FieldError[];
  readonly docUrl: string | null;
  readonly retryAfter: number | null;

  constructor(fields: ApiErrorFields) {
    super(fields.message, { cause: fields.cause });
    this.name = new.target.name;
    this.httpStatus = fields.httpStatus;
    this.apiStatus = fields.apiStatus ?? null;
    this.errors = fields.errors ?? [];
    this.raw = fields.raw ?? null;
    this.requestId = fields.requestId ?? null;
    this.errorCode = fields.errorCode ?? null;
    this.errorType = fields.errorType ?? null;
    this.details = fields.details ?? null;
    this.fields = fields.fields ?? [];
    this.docUrl = fields.docUrl ?? null;
    this.retryAfter = fields.retryAfter ?? null;
    Object.setPrototypeOf(this, new.target.prototype);
  }
}

/** 401 — missing or invalid authentication headers. */
export class AuthenticationError extends PlasgosApiError {}

/** 403 — API key/signature valid but the scope or context is not allowed. */
export class PermissionError extends PlasgosApiError {}

/** 403 — the subscription plan does not include API access. */
export class PlanError extends PermissionError {}

/** 404 — resource not found. */
export class NotFoundError extends PlasgosApiError {}

/** Clock skew: v3 `401 signature_expired` (see `details.server_time`), legacy 408. Also an AuthenticationError. */
export class SignatureExpiredError extends AuthenticationError {}

/** 409 — conflict, e.g. `identity_taken`, `idempotency_in_progress`, legacy duplicate `message_id`. */
export class ConflictError extends PlasgosApiError {}

/** 400/422 — request body failed validation. See `.errors`. */
export class ValidationError extends PlasgosApiError {}

/** 400 — generic bad request without a structured `errors` array. */
export class BadRequestError extends PlasgosApiError {}

/** 429 — `rate_limited` (per minute, retried automatically) or `daily_quota_exceeded`. */
export class RateLimitError extends PlasgosApiError {}

/** >= 500 — server-side failure. */
export class ServerError extends PlasgosApiError {}

/** 502 — the channel provider (e.g. Meta) failed. Retried automatically. */
export class ChannelError extends ServerError {}

/** 503 — the service is temporarily unavailable. Retried automatically. */
export class ServiceUnavailableError extends ServerError {}

/** Network failure, DNS error, timeout — the request never got a response. */
export class TransportError extends PlasgosApiError {
  constructor(message: string, cause?: unknown) {
    super({ message, httpStatus: 0, cause, errorCode: "transport_error" });
  }
}

/** Raised by webhook verification (v3 `Webhook.verify`, legacy `x-hub-token`). */
export class WebhookVerificationError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "WebhookVerificationError";
  }
}

/**
 * Turn a normalised HTTP failure into the most specific typed error.
 */
export function errorFromResponse(params: {
  httpStatus: number;
  apiStatus: number | string | null;
  message: string;
  errors: ValidationIssue[];
  raw: unknown;
  requestId: string | null;
}): PlasgosApiError {
  const base: ApiErrorFields = {
    message: params.message || `Request failed with status ${params.httpStatus}`,
    httpStatus: params.httpStatus,
    apiStatus: params.apiStatus,
    errors: params.errors,
    raw: params.raw,
    requestId: params.requestId,
  };

  const looksLikePlan =
    params.httpStatus === 403 &&
    /tidak menyertakan|tidak menyertakan akses api|does not include api|plan/i.test(params.message);

  switch (params.httpStatus) {
    case 401:
      return new AuthenticationError(base);
    case 403:
      return looksLikePlan ? new PlanError(base) : new PermissionError(base);
    case 404:
      return new NotFoundError(base);
    case 408:
      return new SignatureExpiredError(base);
    case 409:
      return new ConflictError(base);
    case 422:
      return new ValidationError(base);
    case 429:
      return new RateLimitError(base);
    case 400:
      return base.errors && base.errors.length > 0
        ? new ValidationError(base)
        : new BadRequestError(base);
    default:
      if (params.httpStatus >= 500) return new ServerError(base);
      return new PlasgosApiError(base);
  }
}

/**
 * Build a typed error from an API v3 error response (docs/SDK_SPEC.md §5).
 * `body` is the decoded JSON body, or null when it was not JSON.
 */
export function errorFromV3(params: {
  httpStatus: number;
  body: Record<string, unknown> | null;
  requestId: string | null;
  retryAfter: number | null;
}): PlasgosApiError {
  const { httpStatus, body } = params;
  const error = isObject(body?.error) ? (body!.error as Record<string, unknown>) : {};
  const message =
    typeof body?.message === "string" && body.message !== ""
      ? body.message
      : `Plasgos CRM responded with HTTP ${httpStatus}`;
  const code =
    typeof error.code === "string" ? error.code : body === null ? "unexpected_response" : `http_${httpStatus}`;
  const fields: FieldError[] = (Array.isArray(error.fields) ? error.fields : [])
    .filter(isObject)
    .map((field) => ({
      field: String(field.field ?? ""),
      code: String(field.code ?? ""),
      message: String(field.message ?? ""),
    }));
  const meta = isObject(body?.meta) ? (body!.meta as Record<string, unknown>) : {};
  const base: ApiErrorFields = {
    message,
    httpStatus,
    apiStatus: code,
    errors: fields.map((f) => ({ message: f.message, path: f.field === "" ? [] : f.field.split(".") })),
    raw: body,
    requestId: typeof meta.request_id === "string" ? meta.request_id : params.requestId,
    errorCode: code,
    errorType: typeof error.type === "string" ? error.type : null,
    details: isObject(error.details) || Array.isArray(error.details) ? (error.details as Record<string, unknown>) : null,
    fields,
    docUrl: typeof error.doc_url === "string" ? error.doc_url : null,
    retryAfter: params.retryAfter,
  };

  if (httpStatus === 401 && code === "signature_expired") return new SignatureExpiredError(base);
  if (httpStatus === 401) return new AuthenticationError(base);
  if (httpStatus === 403 && code === "not_entitled") return new PlanError(base);
  if (httpStatus === 403) return new PermissionError(base);
  if (httpStatus === 404) return new NotFoundError(base);
  if (httpStatus === 409) return new ConflictError(base);
  if (httpStatus === 400 && fields.length === 0) return new BadRequestError(base);
  if (httpStatus === 400 || httpStatus === 413 || httpStatus === 422) return new ValidationError(base);
  if (httpStatus === 429) return new RateLimitError(base);
  if (httpStatus === 502) return new ChannelError(base);
  if (httpStatus === 503) return new ServiceUnavailableError(base);
  if (httpStatus >= 500) return new ServerError(base);
  return new PlasgosApiError(base);
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
