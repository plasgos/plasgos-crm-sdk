import { randomUUID } from "node:crypto";
import {
  ChannelError,
  PlasgosApiError,
  RateLimitError,
  ServerError,
  ServiceUnavailableError,
  TransportError,
  errorFromV3,
} from "../errors.js";
import { ApiResponse, Page } from "./response.js";
import { signatureHeadersV3 } from "./signature.js";

export type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
export type QueryValue = string | number | boolean | null | undefined;
export type Query = Record<string, QueryValue>;

/** Per-call overrides (docs/SDK_SPEC.md §1.1). */
export interface RequestOptions {
  /** `Idempotency-Key` for this POST; default a UUIDv4 generated once and reused by every retry. */
  idempotencyKey?: string;
  /** Timeout in ms for each attempt of this call. */
  timeout?: number;
  /** Automatic retries for this call (§6). */
  maxRetries?: number;
  /** Extra headers, e.g. tracing. Signing, `Idempotency-Key`, `Content-Type`, `Accept` and `User-Agent` stay with the SDK. */
  headers?: Record<string, string>;
  /** Cancels the call, including pending retries; the abort reason is thrown as is. */
  signal?: AbortSignal;
}

/** Older call style: a bare string is the idempotency key. */
export type KeyOrOptions = string | RequestOptions | null | undefined;

/** Headers the SDK owns: overriding them would break signing or idempotency. */
const RESERVED_HEADERS = new Set(["accept", "user-agent", "plasgos-api-key", "plasgos-timestamp", "plasgos-signature", "idempotency-key", "content-type", "content-length", "host"]);

export interface ApiClientConfig {
  baseUrl: string;
  apiKey: string;
  secretKey: string;
  timeout: number;
  userAgent: string;
  maxRetries: number;
  /** Longest `Retry-After` (seconds) honoured automatically. */
  maxRetryWait: number;
  fetchImpl: typeof fetch;
  /** Test hook: wait `ms` milliseconds. */
  sleep: (ms: number) => Promise<void>;
  /** Test hook: current Unix time in seconds (signing timestamp). */
  clock: () => number;
}

const PREFIX = "/api/v3";
const RETRY_BASE_MS = 500;
const RETRY_CAP_MS = 8000;

/**
 * HTTP layer for API v3 (`/api/v3/*`): signing, idempotency, retry policy, envelope parsing.
 * See docs/SDK_SPEC.md §2, §4–§6.
 */
export class ApiClient {
  constructor(private readonly config: ApiClientConfig) {}

  async request<T = any, M = Record<string, any>>(method: Method, path: string, query: Query = {}, body?: unknown, keyOrOptions?: KeyOrOptions): Promise<ApiResponse<T, M>> {
    const options = normalizeOptions(keyOrOptions);
    const verb = method.toUpperCase() as Method;
    const queryString = buildQuery(query);
    const url = `${this.config.baseUrl.replace(/\/+$/, "")}${PREFIX}/${path.replace(/^\//, "")}${queryString ? `?${queryString}` : ""}`;
    // Path + query exactly as sent — including any sub-path of a custom base URL.
    const requestTarget = url.replace(/^[a-z][a-z0-9+.-]*:\/\/[^/]+/i, "") || "/";
    // Serialised ONCE: the exact bytes signed are the bytes sent.
    const rawBody = body === undefined || body === null ? "" : JSON.stringify(body);
    // One key per intent: generated once here and reused by every retry.
    const key = options.idempotencyKey ?? (verb === "POST" ? randomUUID() : null);
    const maxRetries = options.maxRetries ?? this.config.maxRetries;

    for (let attempt = 0; ; attempt++) {
      options.signal?.throwIfAborted();
      try {
        return await this.attempt<T, M>(verb, url, requestTarget, rawBody, key, options);
      } catch (error) {
        const wait = error instanceof PlasgosApiError ? this.retryWait(error, verb, attempt, maxRetries) : null;
        if (wait === null) throw error;
        await this.config.sleep(wait);
      }
    }
  }

  async requestPage<T = any>(path: string, query: Query = {}, options: RequestOptions = {}): Promise<Page<T>> {
    const response = await this.request<T[]>("GET", path, query, undefined, options);
    return new Page<T>(response, (page) => this.requestPage<T>(path, { ...query, page }, options));
  }

  private async attempt<T, M>(method: Method, url: string, requestTarget: string, rawBody: string, idempotencyKey: string | null, options: RequestOptions): Promise<ApiResponse<T, M>> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      "User-Agent": this.config.userAgent,
      ...options.headers,
      ...signatureHeadersV3(this.config.apiKey, this.config.secretKey, method, requestTarget, rawBody, this.config.clock()),
    };
    if (idempotencyKey !== null) headers["Idempotency-Key"] = idempotencyKey;
    if (rawBody !== "") headers["Content-Type"] = "application/json; charset=utf-8";

    const timeout = options.timeout ?? this.config.timeout;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    const cancel = () => controller.abort();
    options.signal?.addEventListener("abort", cancel, { once: true });
    let response: Response;
    try {
      response = await this.config.fetchImpl(url, { method, headers, body: rawBody === "" ? undefined : rawBody, signal: controller.signal, redirect: "manual" });
    } catch (error) {
      // Cancelled by the caller: not a transport failure, never retried.
      options.signal?.throwIfAborted();
      const timedOut = (error as Error)?.name === "AbortError";
      throw new TransportError(
        timedOut ? `Request to ${url} timed out after ${timeout}ms` : `Network error requesting ${url}: ${(error as Error)?.message}`,
        error,
      );
    } finally {
      clearTimeout(timer);
      options.signal?.removeEventListener("abort", cancel);
    }
    return parse<T, M>(response.status, lowerHeaders(response.headers), await response.text());
  }

  /** Milliseconds to wait before retrying, or null when this error is not retried (§6). */
  private retryWait(error: PlasgosApiError, method: Method, attempt: number, maxRetries: number): number | null {
    if (attempt >= maxRetries) return null;
    if (error instanceof RateLimitError) {
      // Daily quota does not recover in seconds — hand it back to the caller.
      if (error.errorCode !== "rate_limited") return null;
      if (error.retryAfter !== null) return error.retryAfter <= this.config.maxRetryWait ? error.retryAfter * 1000 : null;
      return backoff(attempt);
    }
    // The first request with this Idempotency-Key is still running (e.g. it timed out client-side);
    // retrying with the same key returns its stored answer once it finishes.
    if (error.errorCode === "idempotency_in_progress") return backoff(attempt);
    const transient = error instanceof TransportError || error instanceof ChannelError || error instanceof ServiceUnavailableError;
    // A plain 500 may have partially executed; only reads are retried on it.
    if (transient || (method === "GET" && error instanceof ServerError)) return backoff(attempt);
    return null;
  }
}

function normalizeOptions(keyOrOptions: KeyOrOptions): RequestOptions {
  const options = typeof keyOrOptions === "string" ? { idempotencyKey: keyOrOptions } : (keyOrOptions ?? {});
  for (const name of Object.keys(options.headers ?? {})) {
    if (RESERVED_HEADERS.has(name.toLowerCase())) throw new TypeError(`RequestOptions.headers: "${name}" is set by the SDK`);
  }
  return options;
}

/** Parse a v3 response or throw a typed error (§4, §5). */
export function parse<T, M = Record<string, any>>(status: number, headers: Record<string, string>, text: string): ApiResponse<T, M> {
  const requestId = headers["x-request-id"] ?? null;
  let body: Record<string, any> | null = null;
  if (text !== "") {
    try {
      const decoded = JSON.parse(text);
      body = decoded && typeof decoded === "object" && !Array.isArray(decoded) ? decoded : null;
    } catch {
      body = null;
    }
  }

  if (status < 200 || status >= 300) {
    const retryAfter = /^\d+$/.test(headers["retry-after"] ?? "") ? Number(headers["retry-after"]) : null;
    throw errorFromV3({ httpStatus: status, body, requestId, retryAfter });
  }
  if (text !== "" && (body === null || !("data" in body))) {
    throw new PlasgosApiError({
      message: "Plasgos CRM response is not an API v3 envelope",
      httpStatus: status,
      raw: text,
      requestId,
      errorCode: "unexpected_response",
      errorType: "api_error",
    });
  }

  const meta = body && typeof body.meta === "object" && body.meta !== null ? body.meta : {};
  const int = (value: string | undefined): number | null => (value === undefined || value === "" ? null : Number.parseInt(value, 10));
  return new ApiResponse<T, M>(
    status,
    typeof body?.message === "string" ? body.message : "",
    (body?.data ?? null) as T,
    meta,
    typeof meta.request_id === "string" ? meta.request_id : requestId,
    { limit: int(headers["ratelimit-limit"]), remaining: int(headers["ratelimit-remaining"]), reset: int(headers["ratelimit-reset"]) },
    (headers["idempotent-replayed"] ?? "").toLowerCase() === "true",
    headers,
  );
}

/** RFC 3986 query: null/"" dropped, booleans as "true"/"false", spaces as %20 (§2). */
export function buildQuery(query: Query): string {
  return Object.entries(query)
    .filter(([, value]) => value !== null && value !== undefined && value !== "")
    .map(([key, value]) => `${rfc3986(key)}=${rfc3986(typeof value === "boolean" ? (value ? "true" : "false") : String(value))}`)
    .join("&");
}

function rfc3986(value: string): string {
  return encodeURIComponent(value).replace(/[!'()*]/g, (c) => `%${c.charCodeAt(0).toString(16).toUpperCase()}`);
}

function lowerHeaders(headers: Headers): Record<string, string> {
  const out: Record<string, string> = {};
  headers.forEach((value, name) => {
    out[name.toLowerCase()] = value;
  });
  return out;
}

function backoff(attempt: number): number {
  const cap = Math.min(RETRY_CAP_MS, RETRY_BASE_MS * 2 ** attempt);
  return Math.floor(Math.random() * (cap + 1)); // full jitter
}

/** Path segment, URL-encoded so an id can never alter the path. */
export function segment(value: string): string {
  return encodeURIComponent(String(value));
}
