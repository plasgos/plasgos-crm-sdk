import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { computeSignature, signRequest } from "../src/signature.js";

const vectors = JSON.parse(
  readFileSync(
    fileURLToPath(new URL("../../../docs/vectors.json", import.meta.url)),
    "utf8",
  ),
).vectors as Array<{
  api_key: string;
  secret_key: string;
  timestamp: number;
  signature: string;
}>;

describe("computeSignature", () => {
  it.each(vectors)(
    "matches golden vector for $api_key @ $timestamp",
    ({ api_key, secret_key, timestamp, signature }) => {
      expect(computeSignature(api_key, secret_key, timestamp)).toBe(signature);
    },
  );

  it("is lowercase hex", () => {
    const sig = computeSignature("k", "s", 1);
    expect(sig).toMatch(/^[0-9a-f]{64}$/);
  });
});

describe("signRequest", () => {
  it("returns the three signed headers", () => {
    const headers = signRequest("test_key", "test_secret", 1700000000);
    expect(headers).toEqual({
      "x-api-key": "test_key",
      "x-timestamp": "1700000000",
      "x-signature":
        "e18a0c152134399f8efa60f058ced975a2f24657e231c3bd95351d527a3c1fcb",
    });
  });
});
