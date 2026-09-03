import { createHmac } from "node:crypto";

export interface SignatureHeaders {
  "x-api-key": string;
  "x-timestamp": string;
  "x-signature": string;
  [key: string]: string;
}

/**
 * Compute the signed string exactly as the server does:
 *
 *   signature = lowercase_hex( HMAC_SHA256( secretKey, `${apiKey}:${timestamp}` ) )
 *
 * The timestamp is Unix time in **seconds**. Nothing else (method, path, body)
 * is covered by the signature.
 */
export function computeSignature(
  apiKey: string,
  secretKey: string,
  timestampSeconds: number,
): string {
  return createHmac("sha256", secretKey)
    .update(`${apiKey}:${timestampSeconds}`)
    .digest("hex")
    .toLowerCase();
}

/** Current Unix time in seconds. */
export function currentTimestamp(now: number = Date.now()): number {
  return Math.floor(now / 1000);
}

/** Build the three auth headers for a signed request. */
export function signRequest(
  apiKey: string,
  secretKey: string,
  timestampSeconds: number = currentTimestamp(),
): SignatureHeaders {
  return {
    "x-api-key": apiKey,
    "x-timestamp": String(timestampSeconds),
    "x-signature": computeSignature(apiKey, secretKey, timestampSeconds),
  };
}
