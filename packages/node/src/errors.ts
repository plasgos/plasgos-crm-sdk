export interface ApiErrorFields {
  message: string;
  httpStatus: number;
  apiStatus?: number | string | null;
  errors?: ValidationIssue[];
  raw?: unknown;
  requestId?: string | null;
  cause?: unknown;
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

  constructor(fields: ApiErrorFields) {
    super(fields.message, { cause: fields.cause });
    this.name = new.target.name;
    this.httpStatus = fields.httpStatus;
    this.apiStatus = fields.apiStatus ?? null;
    this.errors = fields.errors ?? [];
    this.raw = fields.raw ?? null;
    this.requestId = fields.requestId ?? null;
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

/** 408 — `x-timestamp` is outside the allowed window (clock skew). */
export class SignatureExpiredError extends PlasgosApiError {}

/** 409 — duplicate `message_id`. */
export class ConflictError extends PlasgosApiError {}

/** 400/422 — request body failed validation. See `.errors`. */
export class ValidationError extends PlasgosApiError {}

/** 400 — generic bad request without a structured `errors` array. */
export class BadRequestError extends PlasgosApiError {}

/** 429 — daily API hit quota exceeded. */
export class RateLimitError extends PlasgosApiError {}

/** >= 500 — server-side failure. */
export class ServerError extends PlasgosApiError {}

/** Network failure, DNS error, timeout — the request never got a response. */
export class TransportError extends PlasgosApiError {
  constructor(message: string, cause?: unknown) {
    super({ message, httpStatus: 0, cause });
  }
}

/** Raised by `verifyWebhook` when the `x-hub-token` does not match or the body is not JSON. */
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
