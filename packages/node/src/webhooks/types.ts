export const WebhookEventName = {
  MESSAGE_RECEIVED: "message.received",
  MESSAGE_STATUSES: "message.statuses",
} as const;

export type WebhookEventName =
  (typeof WebhookEventName)[keyof typeof WebhookEventName];

export interface WebhookEventBase {
  event: WebhookEventName | string;
  event_name: string;
  event_version: number | null;
  event_at: string;
  timestamp: number;
  source: string;
  channel: string;
  provider: string | null;
  user: { id: number | null };
  account: Record<string, unknown> | null;
  conversation: Record<string, unknown> | null;
  message: Record<string, unknown> | null;
  statuses: Record<string, unknown> | null;
  recipient: Record<string, unknown> | null;
  meta: Record<string, unknown> | null;
  data: Record<string, unknown> | null;
}

export interface MessageReceivedEvent extends WebhookEventBase {
  event: "message.received";
}

export interface MessageStatusesEvent extends WebhookEventBase {
  event: "message.statuses";
}

export type WebhookEvent =
  | MessageReceivedEvent
  | MessageStatusesEvent
  | WebhookEventBase;
