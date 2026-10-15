import { createHmac } from "node:crypto";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { Webchat } from "../src/index.js";

interface VectorOptions { widget_id?: string; ttl?: number; now?: number; jti?: string }
interface Vector { name: string; secret: string; identity: Record<string, unknown>; options: VectorOptions; header_json: string; payload_json: string; token: string }

const doc = JSON.parse(readFileSync(fileURLToPath(new URL("../../../docs/vectors-v3.json", import.meta.url)), "utf8"));
const vectors = doc.webchat_identity as Vector[];
const invalid = doc.webchat_identity_invalid as Array<Omit<Vector, "header_json" | "payload_json" | "token">>;

const optionsOf = (options: VectorOptions) => ({
  ...(options.widget_id !== undefined ? { widgetId: options.widget_id } : {}),
  ...(options.ttl !== undefined ? { ttl: options.ttl } : {}),
  ...(options.now !== undefined ? { now: options.now } : {}),
  ...(options.jti !== undefined ? { jti: options.jti } : {}),
});
const decode = (part: string) => Buffer.from(part, "base64url").toString("utf8");

describe("Webchat.signIdentity (SDK_SPEC §8)", () => {
  it.each(vectors.map((v) => [v.name, v] as const))("golden vector: %s", (_name, vector) => {
    const token = Webchat.signIdentity(vector.secret, vector.identity as never, optionsOf(vector.options));
    expect(token).toBe(vector.token);
    const [header, payload] = token.split(".");
    expect(decode(header!)).toBe(vector.header_json);
    expect(decode(payload!)).toBe(vector.payload_json);
  });

  it.each(invalid.map((v) => [v.name, v] as const))("rejects: %s", (_name, vector) => {
    expect(() => Webchat.signIdentity(vector.secret, vector.identity as never, optionsOf(vector.options))).toThrow(/Webchat\.signIdentity/);
  });

  it("defaults: one hour lifetime, current time, random UUID jti, valid HS256 signature", () => {
    const before = Math.floor(Date.now() / 1000);
    const token = Webchat.signIdentity("wcs_secret", { sub: "7" });
    const [header, payload, signature] = token.split(".");
    const claims = JSON.parse(decode(payload!));
    expect(claims.iat).toBeGreaterThanOrEqual(before);
    expect(claims.exp - claims.iat).toBe(Webchat.IDENTITY_DEFAULT_TTL);
    expect(claims.jti).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    expect(signature).toBe(createHmac("sha256", "wcs_secret").update(`${header}.${payload}`).digest("base64url"));
    expect(Webchat.signIdentity("wcs_secret", { sub: "7" })).not.toBe(token);
  });
});
