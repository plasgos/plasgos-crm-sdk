import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { Webhook, WebhookEventType, WebhookVerificationError } from "../src/index.js";

const vectors = JSON.parse(readFileSync(fileURLToPath(new URL("../../../docs/vectors-v3.json", import.meta.url)), "utf8")).webhook as Array<{
  id: string;
  timestamp: number;
  secret: string;
  body: string;
  signature: string;
}>;

describe("Webhook.verify (SDK_SPEC §7)", () => {
  const vector = vectors[0]!;
  const headers = { "Plasgos-Webhook-Id": vector.id, "Plasgos-Webhook-Timestamp": String(vector.timestamp), "Plasgos-Webhook-Signature": vector.signature };

  it("golden vector verifies with any header style", () => {
    expect(Webhook.signatureHeaders(vector.id, vector.timestamp, vector.body, vector.secret)["Plasgos-Webhook-Signature"]).toBe(vector.signature);
    const event = Webhook.verify(vector.body, headers, vector.secret, { now: vector.timestamp });
    expect(event.type).toBe(WebhookEventType.MESSAGE_RECEIVED);
    const lower = Object.fromEntries(Object.entries(headers).map(([k, v]) => [k.toLowerCase(), v]));
    expect(Webhook.verify(Buffer.from(vector.body), lower, [vector.secret], { now: vector.timestamp }).id).toBe(vector.id);
    const server = { HTTP_PLASGOS_WEBHOOK_ID: vector.id, HTTP_PLASGOS_WEBHOOK_TIMESTAMP: String(vector.timestamp), HTTP_PLASGOS_WEBHOOK_SIGNATURE: vector.signature };
    expect(Webhook.verify(vector.body, server, vector.secret, { now: vector.timestamp }).id).toBe(vector.id);
    expect(Webhook.verify(vector.body, new Headers(headers), vector.secret, { now: vector.timestamp }).id).toBe(vector.id);
  });

  it("accepts the previous secret during rotation (two signatures)", () => {
    const fresh = Webhook.signatureHeaders(vector.id, vector.timestamp, vector.body, "whsec_new")["Plasgos-Webhook-Signature"];
    const both = { ...headers, "Plasgos-Webhook-Signature": `${fresh},${vector.signature}` };
    expect(Webhook.verify(vector.body, both, "whsec_new", { now: vector.timestamp }).id).toBe(vector.id);
    expect(Webhook.verify(vector.body, both, vector.secret, { now: vector.timestamp }).id).toBe(vector.id);
    expect(Webhook.verify(vector.body, headers, ["whsec_new", vector.secret], { now: vector.timestamp }).id).toBe(vector.id);
  });

  it.each([
    ["missing headers", vector.body, { "Plasgos-Webhook-Id": vector.id }, vector.timestamp, /headers missing/],
    ["bad timestamp", vector.body, { ...headers, "Plasgos-Webhook-Timestamp": "abc" }, vector.timestamp, /invalid timestamp/],
    ["too old", vector.body, headers, vector.timestamp + 301, /outside tolerance/],
    ["no v1", vector.body, { ...headers, "Plasgos-Webhook-Signature": "v0=abc" }, vector.timestamp, /unsupported signature/],
    ["tampered body", vector.body.replace("msg_1", "msg_2"), headers, vector.timestamp, /signature mismatch/],
  ])("rejects %s", (_name, body, h, now, message) => {
    expect(() => Webhook.verify(body as string, h as Record<string, string>, vector.secret, { now: now as number })).toThrow(message as RegExp);
  });

  it("rejects an id that differs from the header and a non-event body", () => {
    const body = '{"id":"evt_other","type":"message.received"}';
    const signed = Webhook.signatureHeaders(vector.id, vector.timestamp, body, vector.secret);
    expect(() => Webhook.verify(body, signed, vector.secret, { now: vector.timestamp })).toThrow(WebhookVerificationError);
    const notEvent = Webhook.signatureHeaders(vector.id, vector.timestamp, "[]", vector.secret);
    expect(() => Webhook.verify("[]", notEvent, vector.secret, { now: vector.timestamp })).toThrow(/not an event/);
  });
});
