import { createHash, createHmac } from "node:crypto";

/**
 * API v3 request signing (docs/SDK_SPEC.md §2):
 *
 *   string_to_sign = timestamp + "\n" + METHOD + "\n" + request_target + "\n" + hex(sha256(raw_body))
 *   signature      = "v1=" + hex(hmac_sha256(secretKey, string_to_sign))
 *
 * `request_target` is the path + query exactly as sent (e.g. `/api/v3/contacts?page=2`).
 */
export const SIGNATURE_PREFIX = "v1=";

export function stringToSign(timestamp: number, method: string, requestTarget: string, rawBody: string): string {
  return `${timestamp}\n${method.toUpperCase()}\n${requestTarget}\n${createHash("sha256").update(rawBody, "utf8").digest("hex")}`;
}

export function signV3(secretKey: string, timestamp: number, method: string, requestTarget: string, rawBody: string): string {
  return SIGNATURE_PREFIX + createHmac("sha256", secretKey).update(stringToSign(timestamp, method, requestTarget, rawBody), "utf8").digest("hex");
}

export interface V3SignatureHeaders {
  "Plasgos-Api-Key": string;
  "Plasgos-Timestamp": string;
  "Plasgos-Signature": string;
}

export function signatureHeadersV3(
  apiKey: string,
  secretKey: string,
  method: string,
  requestTarget: string,
  rawBody = "",
  timestamp: number = Math.floor(Date.now() / 1000),
): V3SignatureHeaders {
  return {
    "Plasgos-Api-Key": apiKey,
    "Plasgos-Timestamp": String(timestamp),
    "Plasgos-Signature": signV3(secretKey, timestamp, method, requestTarget, rawBody),
  };
}
