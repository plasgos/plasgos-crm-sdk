import { describe, expect, it } from "vitest";
import {
  WebhookEventName,
  WebhookVerificationError,
  verifyWebhook,
} from "../src/webhooks/index.js";

const payload = {
  event: "message.received",
  event_name: "message.received",
  event_version: 1052,
  event_at: "2026-04-08T10:32:15.000Z",
  timestamp: 1775644335,
  source: "crm_chatroom",
  channel: "whatsapp",
  provider: "official",
  user: { id: 42 },
  message: { id: "wamid.X", type: "text" },
};

describe("verifyWebhook", () => {
  it("accepts a matching x-hub-token and returns the parsed event", () => {
    const event = verifyWebhook(
      { "x-hub-token": "secret-token" },
      JSON.stringify(payload),
      { expectedToken: "secret-token" },
    );
    expect(event.event).toBe(WebhookEventName.MESSAGE_RECEIVED);
    expect(event.user.id).toBe(42);
  });

  it("rejects a mismatched token", () => {
    expect(() =>
      verifyWebhook({ "x-hub-token": "wrong" }, JSON.stringify(payload), {
        expectedToken: "secret-token",
      }),
    ).toThrow(WebhookVerificationError);
  });

  it("rejects a missing token when one is expected", () => {
    expect(() =>
      verifyWebhook({}, JSON.stringify(payload), { expectedToken: "secret-token" }),
    ).toThrow(WebhookVerificationError);
  });

  it("skips token check when no expectedToken is given", () => {
    const event = verifyWebhook({}, JSON.stringify(payload));
    expect(event.event).toBe("message.received");
  });

  it("rejects a non-JSON body", () => {
    expect(() =>
      verifyWebhook({ "x-hub-token": "t" }, "not json", { expectedToken: "t" }),
    ).toThrow(WebhookVerificationError);
  });

  it("reads headers from a Headers instance and a Buffer body", () => {
    const headers = new Headers({ "x-hub-token": "t" });
    const event = verifyWebhook(headers, Buffer.from(JSON.stringify(payload)), {
      expectedToken: "t",
    });
    expect(event.timestamp).toBe(1775644335);
  });
});
