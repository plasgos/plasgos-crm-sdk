import type { Account, Envelope } from "../types.js";
import { Resource } from "./base.js";

export class AccountsResource extends Resource {
  /** List connected unofficial WhatsApp accounts. */
  list(): Promise<Envelope<Account[]>> {
    return this.http.request<Account[]>({ method: "GET", path: "/api/v1/accounts" });
  }

  /** Get one connected account by its Mongo ObjectId. */
  get(accountId: string): Promise<Envelope<Account>> {
    return this.http.request<Account>({
      method: "GET",
      path: `/api/v1/accounts/${encodeURIComponent(accountId)}`,
    });
  }
}
