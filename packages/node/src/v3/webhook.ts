import { createHmac, timingSafeEqual } from "node:crypto";
import { WebhookVerificationError } from "../errors.js";
import { SIGNATURE_PREFIX } from "./signature.js";

/**
 * API v3 webhook verification (docs/SDK_SPEC.md §7):
 *
 *   Plasgos-Webhook-Signature = "v1=" + hex(hmac_sha256(signing_secret, id + "." + timestamp + "." + raw_body))
 *
 * During the 24 h after a secret rotation the header carries two `v1=` values; pass both secrets.
 * Deliveries may repeat — drop event ids you have already processed.
 */
export const WebhookEventType = Object.freeze({
  MESSAGE_RECEIVED: "message.received",
  MESSAGE_STATUS_UPDATED: "message.status_updated",
  CONTACT_CREATED: "contact.created",
  CONTACT_UPDATED: "contact.updated",
  LEAD_CREATED: "lead.created",
  LEAD_STAGE_CHANGED: "lead.stage_changed",
  DEAL_WON: "deal.won",
  DEAL_LOST: "deal.lost",
  BROADCAST_COMPLETED: "broadcast.completed",
  FOLLOWUP_ENROLLMENT_COMPLETED: "followup.enrollment.completed",
  FOLLOWUP_ENROLLMENT_STOPPED: "followup.enrollment.stopped",
  WEBHOOK_TEST: "webhook.test",
} as const);

export type WebhookEventTypeName = (typeof WebhookEventType)[keyof typeof WebhookEventType];

export interface WebhookEventV3<T = any> {
  id: string;
  type: WebhookEventTypeName | string;
  api_version: "v3";
  created_at: string;
  data: T;
}

export type HeaderBag = Record<string, string | string[] | undefined> | Headers;

export const DEFAULT_TOLERANCE = 300;

function normalize(headers: HeaderBag): Record<string, string> {
  const out: Record<string, string> = {};
  const add = (name: string, value: string | string[] | undefined) => {
    let key = name.toLowerCase().replace(/_/g, "-");
    if (key.startsWith("http-")) key = key.slice(5);
    out[key] = String(Array.isArray(value) ? value[0] ?? "" : value ?? "").trim();
  };
  if (typeof (headers as Headers).forEach === "function" && typeof (headers as Headers).get === "function") {
    (headers as Headers).forEach((value, name) => add(name, value));
  } else {
    for (const [name, value] of Object.entries(headers as Record<string, string | string[] | undefined>)) add(name, value);
  }
  return out;
}

function hmac(secret: string, payload: string): Buffer {
  return createHmac("sha256", secret).update(payload, "utf8").digest();
}

export const Webhook = {
  DEFAULT_TOLERANCE,

  /**
   * Verify a delivery and return the decoded event.
   * @param secrets current secret, or current + previous during rotation
   */
  verify<T = any>(
    rawBody: string | Buffer,
    headers: HeaderBag,
    secrets: string | string[],
    options: { tolerance?: number; now?: number } = {},
  ): WebhookEventV3<T> {
    const body = Buffer.isBuffer(rawBody) ? rawBody.toString("utf8") : rawBody;
    const h = normalize(headers);
    const id = h["plasgos-webhook-id"] ?? "";
    const timestamp = h["plasgos-webhook-timestamp"] ?? "";
    const signature = h["plasgos-webhook-signature"] ?? "";

    if (!id || !timestamp || !signature) throw new WebhookVerificationError("Webhook verification failed: signature headers missing");
    if (!/^\d+$/.test(timestamp)) throw new WebhookVerificationError("Webhook verification failed: invalid timestamp");
    const now = options.now ?? Math.floor(Date.now() / 1000);
    if (Math.abs(now - Number(timestamp)) > (options.tolerance ?? DEFAULT_TOLERANCE)) {
      throw new WebhookVerificationError("Webhook verification failed: timestamp outside tolerance");
    }

    const candidates = signature
      .split(/[\s,]+/)
      .filter((part) => part.startsWith(SIGNATURE_PREFIX))
      .map((part) => part.slice(SIGNATURE_PREFIX.length));
    if (candidates.length === 0) throw new WebhookVerificationError("Webhook verification failed: unsupported signature version");

    const payload = `${id}.${timestamp}.${body}`;
    let matched = false;
    for (const secret of Array.isArray(secrets) ? secrets : [secrets]) {
      if (typeof secret !== "string" || secret === "") continue;
      const expected = hmac(secret, payload);
      for (const candidate of candidates) {
        const given = /^[0-9a-f]+$/i.test(candidate) ? Buffer.from(candidate, "hex") : Buffer.alloc(0);
        if (given.length === expected.length && timingSafeEqual(given, expected)) matched = true;
      }
    }
    if (!matched) throw new WebhookVerificationError("Webhook verification failed: signature mismatch");

    let event: any;
    try {
      event = JSON.parse(body);
    } catch {
      throw new WebhookVerificationError("Webhook verification failed: body is not valid JSON");
    }
    if (!event || typeof event !== "object" || event.id === undefined || event.type === undefined) {
      throw new WebhookVerificationError("Webhook verification failed: body is not an event");
    }
    if (String(event.id) !== id) throw new WebhookVerificationError("Webhook verification failed: event id does not match header");
    return event as WebhookEventV3<T>;
  },

  /** Headers the CRM would send — for tests and local tooling. */
  signatureHeaders(id: string, timestamp: number, rawBody: string, secret: string): Record<string, string> {
    return {
      "Plasgos-Webhook-Id": id,
      "Plasgos-Webhook-Timestamp": String(timestamp),
      "Plasgos-Webhook-Signature": SIGNATURE_PREFIX + hmac(secret, `${id}.${timestamp}.${rawBody}`).toString("hex"),
    };
  },
};
