import type { Envelope } from "../../types.js";
import { Resource, compact } from "../base.js";

export interface SendOtpInput {
  to: string;
  otpCode?: string | null;
  metadata?: Record<string, unknown> | null;
}

export interface SendOtpResult {
  message_id: string | null;
  otp_code: string | null;
  serial_token: string | null;
}

export class OfficialSharedWabaResource extends Resource {
  /** Send an OTP through a shared WABA serial token. */
  sendOtp(serialToken: string, input: SendOtpInput): Promise<Envelope<SendOtpResult>> {
    return this.http.request<SendOtpResult>({
      method: "POST",
      path: `/api/v1/official/shared-waba/otp/${encodeURIComponent(serialToken)}`,
      body: compact({
        to: input.to,
        otp_code: input.otpCode ?? undefined,
        metadata: input.metadata ?? undefined,
      }),
    });
  }
}
