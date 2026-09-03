import type { Envelope, OfficialSendTemplateInput } from "../../types.js";
import { Resource, compact } from "../base.js";

export class OfficialMessagesResource extends Resource {
  /** Send a WABA official template message. */
  sendTemplate(
    input: OfficialSendTemplateInput,
  ): Promise<Envelope<Record<string, unknown>>> {
    const attachment = input.template.parameters?.attachment;
    const body = {
      phone_number_id: input.phoneNumberId,
      to: input.to,
      template: compact({
        id: input.template.id,
        name: input.template.name,
        category: input.template.category,
        parameters: input.template.parameters
          ? compact({
              variables: input.template.parameters.variables,
              attachment: attachment
                ? compact({
                    type: attachment.type,
                    link: attachment.link,
                    mime_type: attachment.mimeType,
                    filename: attachment.fileName,
                    original_name: attachment.originalName,
                    filesize: attachment.fileSize,
                  })
                : undefined,
            })
          : undefined,
      }),
    };
    return this.http.request({
      method: "POST",
      path: "/api/v1/official/messages",
      body,
    });
  }
}
