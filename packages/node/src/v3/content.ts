/**
 * Builders for message `content` — the same shape as the CRM inbox (docs/SDK_SPEC.md §3.4).
 *
 * ```ts
 * Content.text("Halo");
 * Content.image("https://…/promo.jpg", "Promo");
 * Content.template("66f1…").dynamic("name", "{{full_name}}").custom("order", "INV-1");
 * ```
 */
export type MediaType = "image" | "video" | "audio" | "document";

export interface TextContent {
  type: "text";
  text: { body: string; preview_url: boolean };
}

export interface MediaContent {
  type: MediaType;
  media: { url: string; caption: string | null; file_name: string | null; mime_type: string | null };
}

export interface TemplateVariableValue {
  param: string;
  type: "dynamic" | "custom";
  value: string;
}

export interface TemplateLocation {
  latitude: number;
  longitude: number;
  name: string | null;
  address: string | null;
}

export interface TemplateContentShape {
  type: "template";
  template: { template_id: string; variables: TemplateVariableValue[]; location?: TemplateLocation };
}

const MEDIA_TYPES: readonly MediaType[] = ["image", "video", "audio", "document"];

/** WhatsApp template content. The CRM renders the template; the client only maps variables. */
export class TemplateContent {
  private readonly variables: TemplateVariableValue[] = [];
  private locationValue: TemplateLocation | null = null;

  constructor(private readonly templateId: string) {}

  /** Global placeholder resolved by the CRM at send time, e.g. `{{full_name}}`. */
  dynamic(param: string, placeholder: string): this {
    return this.variable(param, "dynamic", placeholder);
  }

  /** Literal value. */
  custom(param: string, value: string): this {
    return this.variable(param, "custom", value);
  }

  /** Only for templates with a LOCATION header; media headers use the approved template's media. */
  location(latitude: number, longitude: number, name: string | null = null, address: string | null = null): this {
    this.locationValue = { latitude, longitude, name, address };
    return this;
  }

  toJSON(): TemplateContentShape {
    return {
      type: "template",
      template: {
        template_id: this.templateId,
        variables: this.variables.map((v) => ({ ...v })),
        ...(this.locationValue ? { location: { ...this.locationValue } } : {}),
      },
    };
  }

  private variable(param: string, type: "dynamic" | "custom", value: string): this {
    const existing = this.variables.findIndex((v) => v.param === param);
    const entry = { param, type, value };
    if (existing >= 0) this.variables[existing] = entry;
    else this.variables.push(entry);
    return this;
  }
}

export const Content = {
  text(body: string, previewUrl = false): TextContent {
    return { type: "text", text: { body, preview_url: previewUrl } };
  },

  media(type: MediaType, url: string, caption: string | null = null, fileName: string | null = null, mimeType: string | null = null): MediaContent {
    if (!MEDIA_TYPES.includes(type)) {
      throw new TypeError("Content.media type must be one of image, video, audio, document");
    }
    return { type, media: { url, caption, file_name: fileName, mime_type: mimeType } };
  },

  image(url: string, caption: string | null = null): MediaContent {
    return Content.media("image", url, caption);
  },

  video(url: string, caption: string | null = null): MediaContent {
    return Content.media("video", url, caption);
  },

  document(url: string, fileName: string | null = null, caption: string | null = null): MediaContent {
    return Content.media("document", url, caption, fileName);
  },

  template(templateId: string): TemplateContent {
    return new TemplateContent(templateId);
  },
};
