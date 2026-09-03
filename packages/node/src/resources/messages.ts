import { randomUUID } from "node:crypto";
import type { Envelope, Message, SendMessageInput } from "../types.js";
import { Resource, compact } from "./base.js";

export interface SendMessageResult extends Envelope<Message> {
  /** The `message_id` that was sent (generated if you did not supply one). */
  messageId: string;
}

export class MessagesResource extends Resource {
  /**
   * Send an unofficial WhatsApp message.
   *
   * `input.messageId` must be globally unique; if omitted the SDK generates a
   * UUIDv4 and returns it on `result.messageId`.
   */
  async send(input: SendMessageInput): Promise<SendMessageResult> {
    const messageId = input.messageId ?? randomUUID();
    const body = {
      message_id: messageId,
      channel: input.channel,
      account: { account_id: input.account.accountId },
      receiver: {
        phone_number: input.receiver.phoneNumber,
        name: input.receiver.name,
      },
      content: {
        type: input.content.type,
        data: compact({
          text: input.content.data.text,
          url: input.content.data.url,
          caption: input.content.data.caption,
          mime_type: input.content.data.mimeType,
          file_name: input.content.data.fileName,
        }),
      },
    };
    const res = await this.http.request<Message>({
      method: "POST",
      path: "/api/v1/messages",
      body,
    });
    return { ...res, messageId };
  }

  /** List messages sent through the API. */
  list(): Promise<Envelope<Message[]>> {
    return this.http.request<Message[]>({ method: "GET", path: "/api/v1/messages" });
  }

  /** Get one message by its client-supplied id. */
  get(messageId: string): Promise<Envelope<Message>> {
    return this.http.request<Message>({
      method: "GET",
      path: `/api/v1/messages/${encodeURIComponent(messageId)}`,
    });
  }
}
