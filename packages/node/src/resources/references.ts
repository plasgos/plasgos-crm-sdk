import type { Envelope, ListContactsParams, TemplateVariable } from "../types.js";
import { Resource, compact } from "./base.js";

export class ReferencesResource extends Resource {
  /**
   * List available WABA template variables.
   * (Server returns a `{ success, data }` envelope; it is normalised for you.)
   */
  variables(): Promise<Envelope<TemplateVariable[]>> {
    return this.http.request<TemplateVariable[]>({
      method: "GET",
      path: "/api/v1/references/variables",
    });
  }

  /** List CRM contacts, paginated. */
  contacts(params: ListContactsParams = {}): Promise<Envelope<Array<Record<string, unknown>>>> {
    return this.http.request({
      method: "GET",
      path: "/api/v1/references/contacts",
      query: compact({
        page: params.page,
        limit: params.limit,
        search: params.search,
      }),
    });
  }
}
