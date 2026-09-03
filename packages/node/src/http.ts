import {
  PlasgosApiError,
  RateLimitError,
  ServerError,
  TransportError,
  errorFromResponse,
  type ValidationIssue,
} from "./errors.js";
import type { Envelope } from "./types.js";

export interface RequestSpec {
  method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  path: string;
  query?: Record<string, string | number | boolean | undefined>;
  body?: unknown;
  /** Extra headers (auth headers are added by the caller). */
  headers?: Record<string, string>;
}

export interface HttpConfig {
  baseUrl: string;
  timeout: number;
  userAgent: string;
  maxRetries: number;
  fetchImpl: typeof fetch;
  /** Called for every attempt to produce fresh auth headers (signing is time-based). */
  authHeaders: () => Record<string, string>;
}

const RETRY_BASE_MS = 500;
const RETRY_CAP_MS = 8000;

export class HttpClient {
  constructor(private readonly config: HttpConfig) {}

  async request<T = unknown>(spec: RequestSpec): Promise<Envelope<T>> {
    const url = this.buildUrl(spec.path, spec.query);
    const isIdempotent = spec.method === "GET";
    const maxAttempts = isIdempotent ? this.config.maxRetries + 1 : 1;

    let lastError: unknown;
    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      try {
        return await this.attempt<T>(url, spec);
      } catch (err) {
        lastError = err;
        const retryable =
          isIdempotent &&
          attempt < maxAttempts - 1 &&
          (err instanceof TransportError ||
            err instanceof ServerError ||
            err instanceof RateLimitError);
        if (!retryable) throw err;
        await sleep(backoffMs(attempt));
      }
    }
    throw lastError;
  }

  private async attempt<T>(url: string, spec: RequestSpec): Promise<Envelope<T>> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.config.timeout);

    let res: Response;
    try {
      res = await this.config.fetchImpl(url, {
        method: spec.method,
        headers: {
          Accept: "application/json",
          "User-Agent": this.config.userAgent,
          ...(spec.body !== undefined ? { "Content-Type": "application/json" } : {}),
          ...this.config.authHeaders(),
          ...(spec.headers ?? {}),
        },
        body: spec.body !== undefined ? JSON.stringify(spec.body) : undefined,
        signal: controller.signal,
      });
    } catch (err) {
      if ((err as Error)?.name === "AbortError") {
        throw new TransportError(`Request to ${url} timed out after ${this.config.timeout}ms`, err);
      }
      throw new TransportError(`Network error requesting ${url}: ${(err as Error)?.message}`, err);
    } finally {
      clearTimeout(timer);
    }

    const text = await res.text();
    const requestId =
      res.headers.get("x-request-id") ?? res.headers.get("x-correlation-id") ?? null;
    return normalize<T>(res.status, text, requestId);
  }

  private buildUrl(path: string, query?: RequestSpec["query"]): string {
    const url = new URL(path.replace(/^\//, ""), this.config.baseUrl.replace(/\/?$/, "/"));
    if (query) {
      for (const [key, value] of Object.entries(query)) {
        if (value !== undefined) url.searchParams.set(key, String(value));
      }
    }
    return url.toString();
  }
}

function backoffMs(attempt: number): number {
  const exp = Math.min(RETRY_CAP_MS, RETRY_BASE_MS * 2 ** attempt);
  return Math.floor(Math.random() * exp); // full jitter
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Normalise the many server envelope shapes into one `Envelope`, or throw a
 * typed error. See docs/SDK_SPEC.md §4/§5.
 */
export function normalize<T>(
  httpStatus: number,
  bodyText: string,
  requestId: string | null,
): Envelope<T> {
  let body: unknown = null;
  if (bodyText) {
    try {
      body = JSON.parse(bodyText);
    } catch {
      body = bodyText;
    }
  }

  const obj = isRecord(body) ? body : null;
  const ok = httpStatus >= 200 && httpStatus < 300;

  // `{ success, data }` shape (references/variables, entitlement errors)
  if (obj && "success" in obj && !("status" in obj)) {
    if (obj.success === true && ok) {
      return {
        status: httpStatus,
        message: typeof obj.message === "string" ? obj.message : null,
        data: (obj.data ?? null) as T,
        metadata: {},
        raw: body,
        httpStatus,
      };
    }
    throw errorFromResponse({
      httpStatus,
      apiStatus: null,
      message: typeof obj.message === "string" ? obj.message : `Request failed with status ${httpStatus}`,
      errors: extractIssues(obj),
      raw: body,
      requestId,
    });
  }

  const apiStatus = obj && "status" in obj ? (obj.status as number | string) : null;
  const message =
    obj && typeof obj.message === "string" ? (obj.message as string) : null;
  const apiStatusIsError =
    typeof apiStatus === "number" && apiStatus >= 400;

  if (!ok || apiStatusIsError) {
    throw errorFromResponse({
      httpStatus: ok && apiStatusIsError ? (apiStatus as number) : httpStatus,
      apiStatus,
      message: message ?? `Request failed with status ${httpStatus}`,
      errors: obj ? extractIssues(obj) : [],
      raw: body,
      requestId,
    });
  }

  if (!obj) {
    return {
      status: httpStatus,
      message: null,
      data: body as T,
      metadata: {},
      raw: body,
      httpStatus,
    };
  }

  const metadata = isRecord(obj.metadata)
    ? obj.metadata
    : isRecord(obj.meta)
      ? obj.meta
      : {};

  return {
    status: (apiStatus as number | boolean | null) ?? httpStatus,
    message,
    data: ("data" in obj ? obj.data : obj) as T,
    metadata,
    raw: body,
    httpStatus,
  };
}

function extractIssues(obj: Record<string, unknown>): ValidationIssue[] {
  const raw = obj.errors;
  if (!Array.isArray(raw)) return [];
  return raw.map((e) => {
    if (isRecord(e)) {
      return {
        message: typeof e.message === "string" ? e.message : String(e.message ?? ""),
        path: Array.isArray(e.path) ? (e.path as Array<string | number>) : undefined,
      };
    }
    return { message: String(e) };
  });
}

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

export { PlasgosApiError };
