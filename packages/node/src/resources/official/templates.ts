import type { Envelope } from "../../types.js";
import { Resource, compact } from "../base.js";

export type TemplateCategory = "MARKETING" | "UTILITY" | "AUTHENTICATION" | "OTP";

export class OfficialTemplatesResource extends Resource {
  /** List APPROVED templates for a WABA account, optionally filtered by category. */
  list(
    accountId: string,
    params: { category?: TemplateCategory } = {},
  ): Promise<Envelope<Array<Record<string, unknown>>>> {
    return this.http.request({
      method: "GET",
      path: `/api/v1/official/templates/${encodeURIComponent(accountId)}`,
      query: compact({ category: params.category }),
    });
  }
}
