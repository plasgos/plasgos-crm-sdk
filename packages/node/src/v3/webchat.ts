import { createHmac, randomUUID } from "node:crypto";

/**
 * Webchat identity token (docs/SDK_SPEC.md §8): tells the website chat widget who the signed-in customer is.
 *
 * Sign it on YOUR server with the widget's identity secret (`wcs_…`, shown once when the widget is created or the
 * secret is rotated) and hand only the token to the page:
 *
 *   Plasgos("boot", { widgetId: "wgt_…", identityToken: Webchat.signIdentity(secret, { sub: String(user.id), email: user.email }) })
 *
 * The secret must never reach the browser. JWT HS256 with claims `sub`, `email?`, `name?`, `phone?`, `wid?`, `iat`,
 * `exp`, `jti`. Each token is single-use per visitor, so sign a fresh one per page render / sign-in.
 */
export interface WebchatIdentity {
  /** Stable id of the customer in your system (≤ 255 characters). */
  sub: string | number;
  /** Verified attributes; they fill empty fields of the CRM contact. `null`/`""` are left out. */
  email?: string | null;
  name?: string | null;
  phone?: string | null;
}

export interface SignIdentityOptions {
  /** Binds the token to one widget (`wgt_…`) — useful when one server serves several widgets. */
  widgetId?: string;
  /** Lifetime in seconds, 1–86400 (the server rejects longer). Default 3600. */
  ttl?: number;
  /** Unix seconds; for tests. */
  now?: number;
  /** Unique token id (≤ 128 characters); a random UUID by default. */
  jti?: string;
}

export const IDENTITY_DEFAULT_TTL = 3600;
export const IDENTITY_MAX_TTL = 86400;

const base64url = (value: string | Buffer): string => Buffer.from(value).toString("base64url");

function optionalText(identity: WebchatIdentity, key: "email" | "name" | "phone"): string | undefined {
  const value = identity[key];
  if (value === undefined || value === null || value === "") return undefined;
  if (typeof value !== "string") throw new TypeError(`Webchat.signIdentity: ${key} must be a string`);
  return value;
}

export const Webchat = {
  IDENTITY_DEFAULT_TTL,
  IDENTITY_MAX_TTL,

  /** Signed identity token (JWT HS256) for `Plasgos("boot", { identityToken })` / `Plasgos("identify", { token })`. */
  signIdentity(secret: string, identity: WebchatIdentity, options: SignIdentityOptions = {}): string {
    if (typeof secret !== "string" || secret === "") throw new TypeError("Webchat.signIdentity: secret is required");
    const rawSub = identity?.sub;
    const sub = typeof rawSub === "number" && Number.isFinite(rawSub) ? String(rawSub) : rawSub;
    if (typeof sub !== "string" || sub.trim() === "" || sub.length > 255) {
      throw new TypeError("Webchat.signIdentity: sub must be a non-empty string of at most 255 characters");
    }
    const ttl = options.ttl ?? IDENTITY_DEFAULT_TTL;
    if (!Number.isInteger(ttl) || ttl < 1 || ttl > IDENTITY_MAX_TTL) {
      throw new RangeError(`Webchat.signIdentity: ttl must be an integer between 1 and ${IDENTITY_MAX_TTL} seconds`);
    }
    if (options.widgetId !== undefined && (typeof options.widgetId !== "string" || options.widgetId === "")) {
      throw new TypeError("Webchat.signIdentity: widgetId must be a non-empty string");
    }
    const jti = options.jti ?? randomUUID();
    if (typeof jti !== "string" || jti === "" || jti.length > 128) {
      throw new TypeError("Webchat.signIdentity: jti must be a non-empty string of at most 128 characters");
    }
    const iat = options.now ?? Math.floor(Date.now() / 1000);

    // Fixed key order: tokens are byte-identical across the Node, PHP and Python SDKs (docs/vectors-v3.json).
    const payload: Record<string, string | number> = { sub };
    for (const key of ["email", "name", "phone"] as const) {
      const value = optionalText(identity, key);
      if (value !== undefined) payload[key] = value;
    }
    if (options.widgetId !== undefined) payload.wid = options.widgetId;
    payload.iat = iat;
    payload.exp = iat + ttl;
    payload.jti = jti;

    const signingInput = `${base64url(JSON.stringify({ alg: "HS256", typ: "JWT" }))}.${base64url(JSON.stringify(payload))}`;
    return `${signingInput}.${base64url(createHmac("sha256", secret).update(signingInput, "utf8").digest())}`;
  },
};
