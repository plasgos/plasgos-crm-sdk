import { describe, expect, it } from "vitest";
import { AuthenticationError, Client, Content, NotFoundError, ValidationError } from "../src/index.js";

/**
 * Optional smoke test against a real API v3 server. Skipped unless these are set:
 *   PLASGOS_LIVE_BASE_URL  e.g. https://api-crm.sandbox.plasgos.co.id or http://127.0.0.1:3001
 *   PLASGOS_LIVE_API_KEY, PLASGOS_LIVE_SECRET_KEY
 * Read paths and validation only — nothing here can deliver a message to a real customer.
 */
const env = process.env;
const live = Boolean(env.PLASGOS_LIVE_BASE_URL && env.PLASGOS_LIVE_API_KEY && env.PLASGOS_LIVE_SECRET_KEY);
const make = (secret = env.PLASGOS_LIVE_SECRET_KEY!) => new Client(env.PLASGOS_LIVE_API_KEY!, secret, { baseUrl: env.PLASGOS_LIVE_BASE_URL!, maxRetries: 0 });

describe.skipIf(!live)("live API v3", () => {
  it("me + query with reserved & unicode chars", async () => {
    const client = make();
    const me = await client.me.get();
    expect(me.httpStatus).toBe(200);
    expect(me.data.scopes).toContain("account:read");
    expect(me.requestId).toMatch(/^req_/);
    expect((await client.request("GET", "/me", { search: "Budi & Sari/+62 👋", page: 2 })).httpStatus).toBe(200);
  });

  it("wrong secret is rejected", async () => {
    const error = await make("plg_wrong").me.get().catch((e) => e);
    expect(error).toBeInstanceOf(AuthenticationError);
    expect(error.errorCode).toBe("signature_invalid");
  });

  it("channels, endpoints and message read paths", async () => {
    const client = make();
    expect((await client.channels.list()).data.length).toBeGreaterThan(0);
    const endpoints = await client.endpoints.list({ per_page: 5 });
    for (const endpoint of endpoints) expect(["ready", "not_ready", "disconnected"]).toContain(endpoint.status);
    expect((await client.messages.sendOptions({ phone_number: "6280000000001" })).data.contact).toBeNull();
    await expect(client.messages.get("wamid.sdk-live-test-missing")).rejects.toMatchObject({ errorCode: "message_not_found" });
    const invalid = await client.messages.send({ endpoint_id: "x", contact: { id: "a", phone_number: "081234567890" }, content: Content.text("uji") }).catch((e) => e);
    expect(invalid).toBeInstanceOf(ValidationError);
    expect(invalid.fields[0].field).toBe("contact.id");
  });

  it("contacts, pipelines, leads, broadcasts, followups and webhooks read paths", async () => {
    const client = make();
    const { scopes } = (await client.me.get()).data;
    if (scopes.includes("contacts:read")) {
      await expect(client.contacts.lookup({ email: "sdk-live-test-missing@contoh.invalid" })).rejects.toBeInstanceOf(NotFoundError);
      expect((await client.contacts.list({ per_page: 1 })).pagination).toHaveProperty("total");
    }
    if (scopes.includes("leads:read")) {
      for (const pipeline of (await client.pipelines.list()).data) for (const stage of pipeline.stages) expect(["open", "won", "lost"]).toContain(stage.kind);
      await expect(client.leads.get("66f1dddddddddddddddddd99")).rejects.toMatchObject({ errorCode: "lead_not_found" });
    }
    if (scopes.includes("broadcasts:read")) await expect(client.broadcasts.get("66f1eeeeeeeeeeeeeeeeee99")).rejects.toMatchObject({ errorCode: "broadcast_not_found" });
    if (scopes.includes("followups:read")) await expect(client.followups.get("66f1ffffffffffffffffff99")).rejects.toMatchObject({ errorCode: "followup_not_found" });
    if (scopes.includes("webhooks:manage")) await expect(client.webhooks.test("66f1abcdabcdabcdabcdab99")).rejects.toMatchObject({ errorCode: "webhook_not_found" });
  });
});
