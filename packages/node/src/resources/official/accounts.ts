import type { Envelope } from "../../types.js";
import { Resource } from "../base.js";

export class OfficialAccountsResource extends Resource {
  /** List WABA official accounts connected to this API key. */
  list(): Promise<Envelope<Array<Record<string, unknown>>>> {
    return this.http.request({ method: "GET", path: "/api/v1/official/accounts" });
  }

  /** List phone numbers for a given WABA official account. */
  phoneNumbers(accountId: string): Promise<Envelope<Array<Record<string, unknown>>>> {
    return this.http.request({
      method: "GET",
      path: `/api/v1/official/accounts/phone-numbers/${encodeURIComponent(accountId)}`,
    });
  }
}
