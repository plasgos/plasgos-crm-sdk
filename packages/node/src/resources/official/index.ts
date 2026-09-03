import type { HttpClient } from "../../http.js";
import { OfficialAccountsResource } from "./accounts.js";
import { OfficialBroadcastsResource } from "./broadcasts.js";
import { OfficialMessagesResource } from "./messages.js";
import { OfficialSharedWabaResource } from "./shared-waba.js";
import { OfficialTemplatesResource } from "./templates.js";

/** `client.official.*` — WhatsApp Business API (official) endpoints. */
export class OfficialNamespace {
  readonly accounts: OfficialAccountsResource;
  readonly templates: OfficialTemplatesResource;
  readonly messages: OfficialMessagesResource;
  readonly broadcasts: OfficialBroadcastsResource;
  readonly sharedWaba: OfficialSharedWabaResource;

  constructor(http: HttpClient) {
    this.accounts = new OfficialAccountsResource(http);
    this.templates = new OfficialTemplatesResource(http);
    this.messages = new OfficialMessagesResource(http);
    this.broadcasts = new OfficialBroadcastsResource(http);
    this.sharedWaba = new OfficialSharedWabaResource(http);
  }
}

export { OfficialAccountsResource } from "./accounts.js";
export { OfficialTemplatesResource } from "./templates.js";
export { OfficialMessagesResource } from "./messages.js";
export { OfficialBroadcastsResource } from "./broadcasts.js";
export { OfficialSharedWabaResource } from "./shared-waba.js";
export type { TemplateCategory } from "./templates.js";
export type { SendOtpInput, SendOtpResult } from "./shared-waba.js";
