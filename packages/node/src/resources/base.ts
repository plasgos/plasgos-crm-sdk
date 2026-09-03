import type { HttpClient } from "../http.js";

export abstract class Resource {
  constructor(protected readonly http: HttpClient) {}
}

/** Drop `undefined` values so we never send `"key": undefined` style noise. */
export function compact<T extends Record<string, unknown>>(obj: T): T {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(obj)) {
    if (v !== undefined) out[k] = v;
  }
  return out as T;
}
