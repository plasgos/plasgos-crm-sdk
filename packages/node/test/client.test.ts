import { describe, expect, it, vi } from "vitest";
import { PlasgosCrmClient } from "../src/client.js";
import {
  PlanError,
  RateLimitError,
  ServerError,
  SignatureExpiredError,
  ValidationError,
} from "../src/errors.js";

type Handler = (url: string, init: RequestInit) => Response | Promise<Response>;

function mockClient(handler: Handler, opts = {}) {
  const fetchImpl = vi.fn(async (url: any, init: any) =>
    handler(String(url), init ?? {}),
  ) as unknown as typeof fetch;
  const client = new PlasgosCrmClient("ak_live_123", "sk_live_abc", {
    baseUrl: "sandbox",
    fetch: fetchImpl,
    maxRetries: 0,
    ...opts,
  });
  return { client, fetchImpl };
}

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "content-type": "application/json" },
  });
}

describe("signed requests", () => {
  it("sends the signature headers and hits the sandbox base url", async () => {
    const { client, fetchImpl } = mockClient(() =>
      json({ status: 200, message: "ok", data: [], metadata: { timestamp: 1 } }),
    );
    await client.accounts.list();
    const [url, init] = (fetchImpl as any).mock.calls[0];
    expect(url).toBe("https://api-crm.sandbox.plasgos.co.id/api/v1/accounts");
    expect(init.headers["x-api-key"]).toBe("ak_live_123");
    expect(init.headers["x-timestamp"]).toMatch(/^\d+$/);
    expect(init.headers["x-signature"]).toMatch(/^[0-9a-f]{64}$/);
  });

  it("maps camelCase send input to the wire body and returns messageId", async () => {
    let sentBody: any;
    const { client } = mockClient((_url, init) => {
      sentBody = JSON.parse(String(init.body));
      return json({ status: 200, message: "sent", data: { message_id: sentBody.message_id }, metadata: {} });
    });
    const res = await client.messages.send({
      messageId: "m-1",
      channel: "whatsapp",
      account: { accountId: "acc-1" },
      receiver: { phoneNumber: "628123456789", name: "John" },
      content: { type: "document", data: { url: "http://x/y.pdf", mimeType: "application/pdf", fileName: "y.pdf" } },
    });
    expect(sentBody).toEqual({
      message_id: "m-1",
      channel: "whatsapp",
      account: { account_id: "acc-1" },
      receiver: { phone_number: "628123456789", name: "John" },
      content: {
        type: "document",
        data: { url: "http://x/y.pdf", mime_type: "application/pdf", file_name: "y.pdf" },
      },
    });
    expect(res.messageId).toBe("m-1");
  });

  it("generates a message_id when omitted", async () => {
    let sentBody: any;
    const { client } = mockClient((_url, init) => {
      sentBody = JSON.parse(String(init.body));
      return json({ status: 200, message: "sent", data: {}, metadata: {} });
    });
    const res = await client.messages.send({
      channel: "whatsapp",
      account: { accountId: "a" },
      receiver: { phoneNumber: "62811", name: "n" },
      content: { type: "text", data: { text: "hi" } },
    });
    expect(sentBody.message_id).toMatch(/[0-9a-f-]{36}/);
    expect(res.messageId).toBe(sentBody.message_id);
  });

  it("normalises the {success,data} envelope from references/variables", async () => {
    const { client } = mockClient(() =>
      json({ success: true, data: [{ label: "name", value: "{{name}}", example: "Budi" }] }),
    );
    const res = await client.references.variables();
    expect(res.data).toHaveLength(1);
    expect(res.message).toBeNull();
    expect(res.httpStatus).toBe(200);
  });
});

describe("error mapping", () => {
  it("408 -> SignatureExpiredError", async () => {
    const { client } = mockClient(() =>
      json({ status: 408, message: "Request timestamp expired", metadata: { timestamp: 1 } }, 408),
    );
    await expect(client.accounts.list()).rejects.toBeInstanceOf(SignatureExpiredError);
  });

  it("429 -> RateLimitError", async () => {
    const { client } = mockClient(() =>
      json({ success: false, message: "Batas hit API harian tercapai (100/100)" }, 429),
    );
    await expect(client.accounts.list()).rejects.toBeInstanceOf(RateLimitError);
  });

  it("403 plan message -> PlanError", async () => {
    const { client } = mockClient(() =>
      json({ success: false, message: "Paket langganan Anda tidak menyertakan akses API" }, 403),
    );
    await expect(client.accounts.list()).rejects.toBeInstanceOf(PlanError);
  });

  it("422 -> ValidationError with issues", async () => {
    const { client } = mockClient(() =>
      json(
        {
          status: 422,
          message: "Missing or invalid data",
          errors: [{ message: "Phone number must start with +62", path: ["receiver", "phone_number"] }],
        },
        422,
      ),
    );
    const err = await client.messages
      .send({
        channel: "whatsapp",
        account: { accountId: "a" },
        receiver: { phoneNumber: "123", name: "n" },
        content: { type: "text", data: { text: "hi" } },
      })
      .catch((e) => e);
    expect(err).toBeInstanceOf(ValidationError);
    expect(err.errors[0].path).toEqual(["receiver", "phone_number"]);
  });

  it("body-level status >= 400 on a 200 response still throws", async () => {
    const { client } = mockClient(() => json({ status: 404, message: "Account not found", data: null }, 200));
    await expect(client.accounts.get("nope")).rejects.toMatchObject({ httpStatus: 404 });
  });
});

describe("retry", () => {
  it("retries idempotent GET on 500 then succeeds", async () => {
    let calls = 0;
    const { client } = mockClient(
      () => {
        calls += 1;
        return calls === 1
          ? json({ status: 500, message: "boom" }, 500)
          : json({ status: 200, message: "ok", data: [], metadata: {} });
      },
      { maxRetries: 2 },
    );
    const res = await client.accounts.list();
    expect(calls).toBe(2);
    expect(res.httpStatus).toBe(200);
  });

  it("does not retry POST", async () => {
    let calls = 0;
    const { client } = mockClient(
      () => {
        calls += 1;
        return json({ status: 500, message: "boom" }, 500);
      },
      { maxRetries: 3 },
    );
    await expect(
      client.messages.send({
        channel: "whatsapp",
        account: { accountId: "a" },
        receiver: { phoneNumber: "62811", name: "n" },
        content: { type: "text", data: { text: "hi" } },
      }),
    ).rejects.toBeInstanceOf(ServerError);
    expect(calls).toBe(1);
  });
});
