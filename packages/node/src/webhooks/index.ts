import { timingSafeEqual } from "node:crypto";
import { WebhookVerificationError } from "../errors.js";
import type { WebhookEvent } from "./types.js";

export * from "./types.js";
export { WebhookVerificationError } from "../errors.js";

type HeaderBag =
  | Headers
  | Record<string, string | string[] | undefined>
  | Map<string, string>;

function readHeader(headers: HeaderBag, name: string): string | null {
  const lower = name.toLowerCase();
  if (typeof (headers as Headers).get === "function") {
    return (headers as Headers).get(lower);
  }
  if (headers instanceof Map) {
    return headers.get(lower) ?? headers.get(name) ?? null;
  }
  const bag = headers as Record<string, string | string[] | undefined>;
  const value = bag[lower] ?? bag[name];
  if (Array.isArray(value)) return value[0] ?? null;
  return value ?? null;
}

function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ab.length !== bb.length) return false;
  return timingSafeEqual(ab, bb);
}

export interface VerifyWebhookOptions {
  /** The `verify_token` you registered. Required unless you registered without one. */
  expectedToken?: string;
}

/**
 * Verify an inbound webhook request and return the parsed event.
 *
 * Plasgos CRM does not HMAC-sign webhook payloads; it sends your
 * `verify_token` in the `x-hub-token` header. This checks that header with a
 * constant-time compare and parses the JSON body.
 *
 * @param headers  the request headers (Node `IncomingHttpHeaders`, `Headers`, or a Map)
 * @param rawBody  the raw request body as a string or Buffer
 * @param options  `{ expectedToken }`
 */
export function verifyWebhook(
  headers: HeaderBag,
  rawBody: string | Buffer,
  options: VerifyWebhookOptions = {},
): WebhookEvent {
  const received = readHeader(headers, "x-hub-token");

  if (options.expectedToken !== undefined) {
    if (!received || !safeEqual(received, options.expectedToken)) {
      throw new WebhookVerificationError(
        "Webhook verification failed: x-hub-token mismatch",
      );
    }
  }

  const text = typeof rawBody === "string" ? rawBody : rawBody.toString("utf8");
  let parsed: unknown;
  try {
    parsed = JSON.parse(text);
  } catch {
    throw new WebhookVerificationError("Webhook verification failed: body is not valid JSON");
  }
  if (typeof parsed !== "object" || parsed === null) {
    throw new WebhookVerificationError("Webhook verification failed: body is not an object");
  }
  return parsed as WebhookEvent;
}

/**
 * Express middleware. Requires the raw request body: mount either
 * `express.text({ type: () => true })` or `express.json()` (this middleware
 * falls back to re-stringifying a parsed object) before it.
 *
 * ```ts
 * app.post("/webhooks/plasgos",
 *   express.text({ type: () => true }),
 *   plasgosWebhook({ expectedToken: process.env.PLASGOS_WEBHOOK_TOKEN }),
 *   (req, res) => { console.log(req.plasgosEvent); res.sendStatus(200); });
 * ```
 */
export function plasgosWebhook(options: VerifyWebhookOptions = {}) {
  return (req: any, res: any, next: any): void => {
    try {
      const raw: string =
        typeof req.body === "string"
          ? req.body
          : Buffer.isBuffer(req.body)
            ? req.body.toString("utf8")
            : typeof req.rawBody === "string"
              ? req.rawBody
              : JSON.stringify(req.body ?? {});
      req.plasgosEvent = verifyWebhook(req.headers, raw, options);
      next();
    } catch (err) {
      res.status(401).json({ message: (err as Error).message });
    }
  };
}
