import { createHash, createHmac } from "node:crypto";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import {
  AuthenticationError,
  BadRequestError,
  ChannelError,
  Client,
  ConflictError,
  Content,
  NotFoundError,
  PermissionError,
  PlanError,
  PlasgosApiError,
  RateLimitError,
  ServerError,
  ServiceUnavailableError,
  SignatureExpiredError,
  TransportError,
  ValidationError,
  buildQuery,
  signV3,
  stringToSign,
} from "../src/index.js";

const vectors = JSON.parse(readFileSync(fileURLToPath(new URL("../../../docs/vectors-v3.json", import.meta.url)), "utf8"));

interface Recorded {
  url: string;
  method: string;
  headers: Record<string, string>;
  body: string;
}

/** fetch double: answers from a queue and records every request. */
function fakeFetch(responses: Array<{ status: number; body?: unknown; headers?: Record<string, string> } | Error>) {
  const requests: Recorded[] = [];
  const impl = (async (url: string, init: RequestInit) => {
    requests.push({ url, method: String(init.method), headers: init.headers as Record<string, string>, body: (init.body as string) ?? "" });
    const next = responses.shift();
    if (!next) throw new Error("no more fake responses");
    if (next instanceof Error) throw next;
    const text = next.body === undefined ? "" : typeof next.body === "string" ? next.body : JSON.stringify(next.body);
    return new Response(text === "" ? null : text, { status: next.status, headers: next.headers });
  }) as unknown as typeof fetch;
  return { impl, requests };
}

const ok = (data: unknown = {}, meta: Record<string, unknown> = { request_id: "req_1" }) => ({ status: 200, body: { success: true, message: "OK", data, meta } });
const fail = (status: number, code: string, type = "invalid_request", extra: Record<string, unknown> = {}, headers: Record<string, string> = {}) => ({
  status,
  headers,
  body: { success: false, message: `Galat ${code}`, error: { code, type, details: null, fields: [], doc_url: `https://docs/${code}`, ...extra }, meta: { request_id: "req_err" } },
});

function client(responses: Parameters<typeof fakeFetch>[0], options: Record<string, unknown> = {}) {
  const fake = fakeFetch(responses);
  const sleeps: number[] = [];
  let now = 1_700_000_000;
  const c = new Client("ak_test", "sk_test", {
    baseUrl: "https://crm.example",
    fetch: fake.impl,
    sleep: async (ms) => { sleeps.push(ms); now += 1; },
    clock: () => now,
    ...options,
  });
  return { client: c, requests: fake.requests, sleeps };
}

describe("signing (SDK_SPEC §2)", () => {
  it.each(vectors.request as any[])("matches golden vector $request_target", (v: any) => {
    expect(stringToSign(v.timestamp, v.method, v.request_target, v.body)).toBe(v.string_to_sign);
    expect(signV3(v.secret_key, v.timestamp, v.method, v.request_target, v.body)).toBe(v.signature);
  });

  it("signs method, path and the exact body bytes sent", async () => {
    const { client: c, requests } = client([{ status: 202, body: { success: true, message: "x", data: { id: "m1" }, meta: {} } }]);
    await c.messages.send({ endpoint_id: "e1", content: Content.text("Halo https://x/y ✓") }, "key-1");
    const r = requests[0]!;
    expect(r.url).toBe("https://crm.example/api/v3/messages");
    expect(r.body).toBe('{"endpoint_id":"e1","content":{"type":"text","text":{"body":"Halo https://x/y ✓","preview_url":false}}}');
    const sts = `${r.headers["Plasgos-Timestamp"]}\nPOST\n/api/v3/messages\n${createHash("sha256").update(r.body).digest("hex")}`;
    expect(r.headers["Plasgos-Signature"]).toBe(`v1=${createHmac("sha256", "sk_test").update(sts).digest("hex")}`);
    expect(r.headers["Plasgos-Api-Key"]).toBe("ak_test");
    expect(r.headers["Idempotency-Key"]).toBe("key-1");
  });

  it("signs the query as sent (RFC 3986, nulls dropped, booleans)", async () => {
    const { client: c, requests } = client([ok([])]);
    await c.contacts.list({ search: "budi santoso", tag: null, ready: true, page: 2, empty: "" });
    expect(requests[0]!.url).toBe("https://crm.example/api/v3/contacts?search=budi%20santoso&ready=true&page=2");
    expect(buildQuery({ q: "a!b*(c)'" })).toBe("q=a%21b%2A%28c%29%27");
  });

  it("encodes path segments so an id cannot alter the path", async () => {
    const { client: c, requests } = client([ok()]);
    await c.contacts.get("../webhooks?x=1");
    expect(requests[0]!.url).toBe("https://crm.example/api/v3/contacts/..%2Fwebhooks%3Fx%3D1");
  });

  it("an action POST without input sends {}", async () => {
    const { client: c, requests } = client([ok()]);
    await c.broadcasts.cancel("b1");
    expect(requests[0]!.body).toBe("{}");
  });
});

describe("idempotency & retries (SDK_SPEC §6)", () => {
  it("POST gets one generated key reused across retries; timestamp recomputed", async () => {
    const { client: c, requests } = client([new TypeError("socket hang up"), fail(503, "service_unavailable", "service_unavailable"), ok()]);
    await c.contacts.upsert({ phone_number: "0812" });
    expect(requests).toHaveLength(3);
    const keys = new Set(requests.map((r) => r.headers["Idempotency-Key"]));
    expect(keys.size).toBe(1);
    expect([...keys][0]).toMatch(/^[0-9a-f-]{36}$/);
    expect(new Set(requests.map((r) => r.headers["Plasgos-Timestamp"])).size).toBe(3);
  });

  it("rate_limited honours Retry-After within maxRetryWait", async () => {
    const { client: c, sleeps } = client([fail(429, "rate_limited", "rate_limit_error", {}, { "Retry-After": "2" }), ok()]);
    await c.me.get();
    expect(sleeps).toEqual([2000]);
  });

  it("a long Retry-After and daily_quota_exceeded are handed back", async () => {
    let { client: c } = client([fail(429, "rate_limited", "rate_limit_error", {}, { "Retry-After": "120" })]);
    await expect(c.me.get()).rejects.toMatchObject({ errorCode: "rate_limited", retryAfter: 120 });
    ({ client: c } = client([fail(429, "daily_quota_exceeded", "rate_limit_error")]));
    await expect(c.me.get()).rejects.toBeInstanceOf(RateLimitError);
  });

  it("a plain 500 retries reads but not writes", async () => {
    const read = client([fail(500, "internal_error", "api_error"), ok()]);
    await read.client.me.get();
    expect(read.requests).toHaveLength(2);
    const write = client([fail(500, "internal_error", "api_error")]);
    await expect(write.client.leads.create({ contact_id: "c" })).rejects.toBeInstanceOf(ServerError);
    expect(write.requests).toHaveLength(1);
  });

  it("502 is retried for writes too; 4xx never", async () => {
    const channel = client([fail(502, "channel_provider_error", "channel_error"), ok()]);
    await channel.client.messages.send({});
    expect(channel.requests).toHaveLength(2);
    const conflict = client([fail(409, "identity_taken", "conflict")]);
    await expect(conflict.client.contacts.create({})).rejects.toBeInstanceOf(ConflictError);
    expect(conflict.requests).toHaveLength(1);
  });

  it("gives up after maxRetries", async () => {
    const { client: c, requests } = client([new TypeError("x"), new TypeError("x"), new TypeError("x")], { maxRetries: 2 });
    await expect(c.me.get()).rejects.toMatchObject({ errorCode: "transport_error" });
    expect(requests).toHaveLength(3);
  });
});

describe("errors (SDK_SPEC §5)", () => {
  it("maps the v3 envelope onto every field", async () => {
    const { client: c } = client([
      { status: 422, body: { success: false, message: "Isi permintaan tidak sesuai format", error: { code: "validation_failed", type: "invalid_request", details: { a: 1 }, fields: [{ field: "recipient.phone_number", code: "required", message: "Wajib diisi" }], doc_url: "https://docs/x" }, meta: { request_id: "req_9" } } },
    ]);
    const error = (await c.contacts.create({}).catch((e) => e)) as ValidationError;
    expect(error).toBeInstanceOf(ValidationError);
    expect(error).toMatchObject({ message: "Isi permintaan tidak sesuai format", httpStatus: 422, errorCode: "validation_failed", errorType: "invalid_request", details: { a: 1 }, docUrl: "https://docs/x", requestId: "req_9" });
    expect(error.fields).toEqual([{ field: "recipient.phone_number", code: "required", message: "Wajib diisi" }]);
    expect(error.errors).toEqual([{ message: "Wajib diisi", path: ["recipient", "phone_number"] }]);
  });

  it.each([
    [401, "api_key_invalid", AuthenticationError],
    [401, "signature_expired", SignatureExpiredError],
    [403, "not_entitled", PlanError],
    [403, "scope_missing", PermissionError],
    [404, "contact_not_found", NotFoundError],
    [409, "identity_taken", ConflictError],
    [400, "malformed_json", BadRequestError],
    [413, "payload_too_large", ValidationError],
    [429, "daily_quota_exceeded", RateLimitError],
    [502, "graph_error", ChannelError],
    [503, "service_unavailable", ServiceUnavailableError],
  ])("%i %s → %o", async (status, code, type) => {
    const { client: c } = client([fail(status as number, code as string)], { maxRetries: 0 });
    const error = await c.contacts.create({}).catch((e) => e);
    expect(error).toBeInstanceOf(type);
    expect(error.errorCode).toBe(code);
  });

  it("signature_expired is also an AuthenticationError; 502/503 are ServerErrors", () => {
    expect(new SignatureExpiredError({ message: "x", httpStatus: 401 })).toBeInstanceOf(AuthenticationError);
    expect(new ChannelError({ message: "x", httpStatus: 502 })).toBeInstanceOf(ServerError);
  });

  it("non-envelope responses raise unexpected_response", async () => {
    let { client: c } = client([{ status: 200, body: "<html>" }]);
    await expect(c.me.get()).rejects.toMatchObject({ errorCode: "unexpected_response" });
    ({ client: c } = client([{ status: 500, body: "<html>" }], { maxRetries: 0 }));
    const error = await c.me.get().catch((e) => e);
    expect(error).toBeInstanceOf(ServerError);
    expect(error.errorCode).toBe("unexpected_response");
    ({ client: c } = client([{ status: 200 }]));
    expect((await c.me.get()).data).toBeNull();
  });

  it("network failures are TransportErrors", async () => {
    const { client: c } = client([new TypeError("ECONNREFUSED")], { maxRetries: 0 });
    const error = await c.me.get().catch((e) => e);
    expect(error).toBeInstanceOf(TransportError);
    expect(error).toBeInstanceOf(PlasgosApiError);
  });
});

describe("responses (SDK_SPEC §4)", () => {
  it("exposes meta, request id, rate limit, replay and headers", async () => {
    const { client: c } = client([{ status: 201, body: { success: true, message: "Kontak dibuat", data: { id: "c1" }, meta: { request_id: "req_7", operation: "created" } }, headers: { "RateLimit-Limit": "120", "RateLimit-Remaining": "119", "RateLimit-Reset": "30", "Idempotent-Replayed": "true", "X-Request-Id": "req_hdr" } }]);
    const r = await c.contacts.upsert({ phone_number: "0812" });
    expect(r.httpStatus).toBe(201);
    expect(r.message).toBe("Kontak dibuat");
    expect(r.data).toEqual({ id: "c1" });
    expect(r.meta.operation).toBe("created");
    expect(r.requestId).toBe("req_7");
    expect(r.rateLimit).toEqual({ limit: 120, remaining: 119, reset: 30 });
    expect(r.replayed).toBe(true);
    expect(r.header("x-request-id")).toBe("req_hdr");
  });

  it("autoPaging walks every page with the same query", async () => {
    const page = (n: number, items: unknown[], next: boolean) => ok(items, { request_id: "r", pagination: { page: n, per_page: 2, total: 3, total_pages: 2, has_next_page: next, has_prev_page: n > 1 } });
    const { client: c, requests } = client([page(1, [{ id: 1 }, { id: 2 }], true), page(2, [{ id: 3 }], false)]);
    const first = await c.contacts.list({ tag: "vip", per_page: 2 });
    expect(first.length).toBe(2);
    expect([...first].map((x: any) => x.id)).toEqual([1, 2]);
    const all: unknown[] = [];
    for await (const contact of first.autoPaging()) all.push(contact);
    expect(all).toEqual([{ id: 1 }, { id: 2 }, { id: 3 }]);
    expect(requests[1]!.url).toBe("https://crm.example/api/v3/contacts?tag=vip&per_page=2&page=2");
  });
});

describe("Content builders (SDK_SPEC §3.4)", () => {
  it("text, media and template shapes", () => {
    expect(Content.text("Halo")).toEqual({ type: "text", text: { body: "Halo", preview_url: false } });
    expect(Content.document("https://x/inv.pdf", "inv.pdf")).toEqual({ type: "document", media: { url: "https://x/inv.pdf", caption: null, file_name: "inv.pdf", mime_type: null } });
    expect(() => Content.media("template" as any, "https://x")).toThrow(TypeError);
    const template = Content.template("tpl").dynamic("name", "{{full_name}}").custom("order", "INV-1").custom("name", "Budi").location(-6.2, 106.8, "Toko");
    expect(JSON.parse(JSON.stringify(template))).toEqual({
      type: "template",
      template: {
        template_id: "tpl",
        variables: [{ param: "name", type: "custom", value: "Budi" }, { param: "order", type: "custom", value: "INV-1" }],
        location: { latitude: -6.2, longitude: 106.8, name: "Toko", address: null },
      },
    });
  });
});

describe("construction", () => {
  it("rejects missing credentials", () => {
    expect(() => new Client("", "s")).toThrow(TypeError);
    expect(() => new Client("k", "")).toThrow(TypeError);
  });
});
