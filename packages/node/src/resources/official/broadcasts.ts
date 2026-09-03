import type {
  Envelope,
  ListBroadcastsParams,
  OfficialBroadcastInput,
} from "../../types.js";
import { Resource, compact } from "../base.js";

export class OfficialBroadcastsResource extends Resource {
  /**
   * Create a WABA official broadcast (HTTP 201). Max 1000 recipients/day,
   * grouped by the `scheduledAt` date. `requestId` is the idempotency key.
   */
  create(input: OfficialBroadcastInput): Promise<Envelope<Record<string, unknown>>> {
    const body = compact({
      account_id: input.accountId,
      template_id: input.templateId,
      phone_number_id: input.phoneNumberId,
      name: input.name,
      description: input.description,
      scheduled_at: input.scheduledAt,
      request_id: input.requestId,
      variables: input.variables,
      location: input.location,
      recipients: input.recipients.map((r) =>
        compact({
          phone_number: r.phoneNumber,
          attachment: r.attachment
            ? compact({
                type: r.attachment.type,
                link: r.attachment.link,
                mime_type: r.attachment.mimeType,
                file_name: r.attachment.fileName,
              })
            : undefined,
        }),
      ),
    });
    return this.http.request({
      method: "POST",
      path: "/api/v1/official/broadcasts",
      body,
    });
  }

  /** List WABA official broadcasts created through the API. */
  list(
    params: ListBroadcastsParams = {},
  ): Promise<Envelope<Array<Record<string, unknown>>>> {
    return this.http.request({
      method: "GET",
      path: "/api/v1/official/broadcasts",
      query: compact({
        page: params.page,
        limit: params.limit,
        status: params.status,
        search: params.search,
      }),
    });
  }

  /** Get delivery monitoring for one broadcast. */
  monitoring(broadcastId: string): Promise<Envelope<Record<string, unknown>>> {
    return this.http.request({
      method: "GET",
      path: `/api/v1/official/broadcasts/${encodeURIComponent(broadcastId)}/monitoring`,
    });
  }
}
