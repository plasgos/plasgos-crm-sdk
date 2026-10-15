// Dibangkitkan dari openapi/openapi-v3.json oleh scripts/generate-types.mjs — jangan diubah manual.
// API 3.0.0-rc.2

import type { TemplateContent } from "./content.js";

export type Meta = {
  request_id: string;
};

export type Pagination = {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next_page: boolean;
  has_prev_page: boolean;
};

export type ErrorObject = {
  /** Kode mesin yang stabil — bercabanglah dengan ini. Katalog lengkap (status HTTP & arti) ada di `components.x-error-codes`; kode baru bisa bertambah. */
  code: string;
  type: "invalid_request" | "authentication_error" | "permission_error" | "not_found" | "conflict" | "rate_limit_error" | "api_error" | "channel_error" | "service_unavailable";
  details: Record<string, unknown> | null;
  fields: Array<{
    field: string;
    code: string;
    message: string;
  }>;
  doc_url: string;
};

export type Me = {
  tenant: {
    id: string;
    name: string;
  };
  api_key: {
    /** 6 karakter awal untuk identifikasi */
    prefix: string;
    status: "active";
    approved_at?: string | null;
  };
  scopes: Array<Scope>;
  quota: {
    /** null = tak terbatas */
    daily_limit: number | null;
    used_today: number;
    resets_at: string;
    per_minute_limit: number;
  };
  webhooks: {
    count?: number;
  };
};

export type Scope = "account:read" | "channels:read" | "messages:read" | "messages:write" | "contacts:read" | "contacts:write" | "leads:read" | "leads:write" | "broadcasts:read" | "broadcasts:write" | "followups:read" | "followups:write" | "webhooks:manage";

/** Daftar terbuka; channel baru bisa bertambah — perlakukan nilai yang belum dikenal sebagai channel lain, jangan gagal. `webchat` = widget chat di website tenant (identitas `external_id`: id pengunjung `vis_…` atau `uid:<widget_id>:<sub>` bila pengunjung login di website; berskop widget → sebut `endpoint_id`). */
export type ChannelName = "whatsapp" | "instagram" | "messenger" | "webchat";

/** `official` = WhatsApp Business Platform / Meta Graph; `unofficial` = WhatsApp Web (perangkat tersambung). */
export type Transport = "official" | "unofficial";

export type Channel = {
  name: ChannelName;
  label: string;
  transports: Array<Transport>;
  /** Termasuk paket langganan tenant */
  entitled: boolean;
  /** Channel benar-benar bisa dipakai mengirim saat ini */
  operational: boolean;
};

export type Endpoint = {
  /** endpoint_id */
  id: string;
  channel: ChannelName;
  /** null untuk channel tanpa transport (Instagram, Messenger) */
  transport: Transport | null;
  /** Nama tampilan; bisa diganti tenant di dashboard */
  label: string;
  /** Alamat asli: nomor E.164 (WhatsApp) atau @username (Instagram) — tidak ikut berubah bila label diganti */
  address: string | null;
  status: "ready" | "not_ready" | "disconnected";
  capabilities: {
    /** Bisnis boleh memulai percakapan: WhatsApp resmi (dengan template) & WhatsApp perangkat */
    can_initiate: boolean;
    requires_template_outside_window: boolean;
    content_types: Array<ContentType>;
    /** Bisa dipakai `POST /broadcasts`. v3.0: hanya WhatsApp resmi. */
    broadcast: boolean;
  };
  /** Nama terverifikasi/akun di channel */
  name: string | null;
  /** Status mentah dari penyedia (mis. connected, active, disconnected) */
  status_detail: string | null;
  created_at: string | null;
};

export type Template = {
  /** Dipakai sebagai content.template.template_id */
  id: string;
  /** Id template di Meta; juga diterima sebagai template_id */
  meta_template_id: string | null;
  name: string;
  language: string | null;
  category: "marketing" | "utility" | "authentication" | null;
  status: "approved";
  /** Teks body dengan placeholder */
  preview: string | null;
  /** Variabel yang wajib dipetakan di content.template.variables (kunci param persis: named, header.N, button.N) */
  parameters: Array<{
    param: string;
    component: "header" | "body" | "button";
    example?: string | null;
  }>;
  /** Format header; media/lokasi bukan variabel */
  header: {
    format?: "text" | "image" | "video" | "document" | "location";
  } | null;
  parameter_format: "named" | "positional";
};

export type TemplateVariable = {
  key: string;
  placeholder: string;
  label: string;
  example?: string | null;
};

export type ContentType = "text" | "image" | "video" | "audio" | "document" | "template";

export type MessageStatus = "pending" | "sent" | "delivered" | "read" | "failed";

export type TemplateVariableValue = {
  /** Nama/nomor parameter template */
  param: string;
  /** `dynamic` = placeholder global (mis. `{{full_name}}`) diisi saat kirim; `custom` = nilai apa adanya. */
  type: "dynamic" | "custom";
  value: string;
};

/** Tepat satu kunci isi sesuai `type` (text | media | template). */
export type Content = {
  type: "text";
  text: {
    body: string;
    preview_url?: boolean;
  };
} | {
  type: "image" | "video" | "audio" | "document";
  /** URL diunduh oleh channel/server — wajib alamat publik (alamat privat/lokal ditolak `media_url_rejected`). */
  media: {
    /** URL https yang bisa diunduh publik */
    url: string;
    caption?: string | null;
    file_name?: string | null;
    mime_type?: string | null;
  };
} | {
  type: "template";
  template: {
    template_id: string;
    variables?: Array<TemplateVariableValue>;
    /** Wajib hanya untuk template berheader LOKASI. Header gambar/video/dokumen memakai media template yang disetujui. */
    location?: {
      latitude: number;
      longitude: number;
      name?: string | null;
      address?: string | null;
    } | null;
  };
};

export type SendMessageRequest = {
  endpoint_id: string;
  /** Penerima. Instagram/Messenger: `channel` + `external_id` (pelanggan harus pernah menghubungi akun ini). `email` = kontak yang sudah ada. Kontak BARU hanya dibuat untuk WhatsApp (dari `phone_number`), memakai field profil. */
  contact: ContactRef;
  content: Content | TemplateContent;
  /** Rujukan bebas dari klien (mis. id pesanan); dikembalikan di `Message.client_reference`. */
  client_reference?: string | null;
};

export type SendOptions = {
  /** Null bila penerima belum terdaftar sebagai kontak. */
  contact: null | {
    id: string;
    name: string | null;
    /** E.164 */
    phone_number: string | null;
  };
  options: Array<{
    endpoint: EndpointSummary;
    /** Ada format pesan yang sah ke penerima ini lewat titik kirim ini. */
    can_send: boolean;
    /** Hanya template yang sah (mis. di luar jendela 24 jam WhatsApp resmi). */
    requires_template: boolean;
    content_types: Array<string>;
    reason: string | null;
    reason_label: string | null;
    existing_conversation_id: string | null;
  }>;
};

export type Message = {
  id: string;
  conversation_id: string;
  contact_id: string | null;
  endpoint_id: string | null;
  channel: ChannelName;
  direction: "inbound" | "outbound";
  /** Bentuk sama dengan `Content`. Template diringkas: `template_id`, `name`, `language`, `category`, `preview`. Jenis lain (lokasi, interaktif, …) dikirim apa adanya di bawah kuncinya. */
  content: Record<string, unknown>;
  status: MessageStatus;
  error: {
    code?: string;
    message?: string;
  } | null;
  client_reference: string | null;
  created_at: string;
  /** Waktu perubahan status terakhir yang tercatat. */
  timestamp: string | null;
};

/** Minimal salah satu dari phone_number atau email. */
export type ContactInput = {
  name?: string | null;
  phone_number?: string | null;
  /** Disimpan huruf kecil */
  email?: string | null;
  company_name?: string | null;
  tags?: Array<string>;
  group_ids?: Array<string>;
  lifecycle_stage?: string | null;
  locale?: string | null;
  timezone?: string | null;
  /** Digabung per kunci; nilai null menghapus kunci. Kunci yang punya definisi field kustom divalidasi tipenya (`invalid_custom_field`). */
  custom_fields?: Record<string, unknown>;
};

/** Hanya field yang dikirim yang diubah. `tags` & `group_ids` berbentuk `{add, remove}` (tak pernah menimpa seluruh daftar). */
export type ContactPatch = {
  name?: string | null;
  /** Dinormalisasi; nomor milik kontak lain → `identity_taken` */
  phone_number?: string;
  email?: string | null;
  company_name?: string | null;
  lifecycle_stage?: string | null;
  /** Digabung per kunci; nilai null menghapus kunci */
  custom_fields?: Record<string, unknown>;
  tags?: AddRemove;
  group_ids?: AddRemove;
  locale?: string | null;
  timezone?: string | null;
};

export type AddRemove = {
  add?: Array<string>;
  remove?: Array<string>;
};

export type Contact = {
  id: string;
  name: string | null;
  /** E.164 */
  phone_number: string | null;
  email: string | null;
  company_name: string | null;
  tags: Array<string>;
  group_ids: Array<string>;
  lifecycle_stage: string | null;
  custom_fields: Record<string, unknown>;
  source: string;
  created_at: string;
  updated_at: string;
  locale: string | null;
  timezone: string | null;
};

export type ContactGroup = {
  id: string;
  name: string;
  total_contacts: number;
};

export type Pipeline = {
  id: string;
  name: string;
  is_default: boolean;
  stages: Array<{
    id: string;
    name: string;
    order: number;
    kind: "open" | "won" | "lost";
  }>;
};

export type CreateLeadRequest = {
  /** Kontak; dicari, atau DIBUAT bila belum ada dan penandanya `phone_number`/`email` (profil hanya untuk kontak baru). Identitas channel harus sudah dikenal. */
  contact: ContactRef;
  pipeline_id: string;
  /** Bawaan = stage pertama */
  stage_id?: string | null;
  score?: number | null;
  tags?: Array<string>;
  /** Catatan pertama lead. */
  note?: string | null;
  /** Asal lead, mis. wordpress:cf7:12 */
  source_detail?: string | null;
};

export type Lead = {
  id: string;
  contact_id: string;
  pipeline_id: string;
  stage: {
    id: string;
    name: string;
  };
  status: "open" | "converted" | "lost";
  score: number | null;
  tags: Array<string>;
  source: string;
  source_detail: string | null;
  created_at: string;
  updated_at: string;
};

export type BroadcastStatus = "scheduled" | "processing" | "paused" | "completed" | "cancelled" | "failed";

export type CreateBroadcastRequest = {
  name: string;
  description?: string | null;
  /** Titik kirim WhatsApp resmi (`phone_number_id`). v3.0 hanya broadcast resmi. */
  endpoint_id: string;
  /** Broadcast WhatsApp resmi wajib template. */
  content: {
    type: "template";
    template: {
      template_id: string;
      variables?: Array<TemplateVariableValue>;
      /** Wajib hanya untuk template berheader LOKASI. Header gambar/video/dokumen memakai media template yang disetujui. */
      location?: {
        latitude: number;
        longitude: number;
        name?: string | null;
        address?: string | null;
      } | null;
    };
  } | TemplateContent;
  /** TEPAT SATU dari contact_ids, group_ids, recipients. Maks. 1000 penerima setelah diurai (sama dengan kuota harian integrasi). */
  audience: {
    contact_ids?: Array<string>;
    /** Anggota grup diurai saat permintaan diterima. */
    group_ids?: Array<string>;
    recipients?: Array<{
      phone_number: string;
      name?: string | null;
    }>;
  };
  /** ISO 8601 dengan zona waktu; null = sekarang. */
  scheduled_at?: string | null;
};

export type Broadcast = {
  id: string;
  name: string;
  description: string | null;
  endpoint_id: string;
  channel: ChannelName;
  transport: Transport;
  status: BroadcastStatus;
  scheduled_at: string;
  /** Hitungan penerima per status publik (antrean/jeda = pending, ditolak = failed). */
  summary: {
    total: number;
    pending: number;
    sent: number;
    delivered: number;
    read: number;
    failed: number;
    cancelled: number;
  };
  created_at: string;
  completed_at: string | null;
};

export type BroadcastRecipient = {
  contact_id: string | null;
  phone_number: string;
  status: BroadcastRecipientStatus;
  message_id: string | null;
  error: {
    code?: string;
    message?: string;
  } | null;
  updated_at: string | null;
};

export type Followup = {
  id: string;
  title: string;
  category?: string | null;
  /** draft = belum aktif; active = menerima pendaftaran; stopped = dijeda/dibatalkan di dashboard. */
  status: "draft" | "active" | "stopped";
  endpoint: {
    id: string | null;
    channel: ChannelName;
    transport: string | null;
  };
  steps: Array<{
    order: number;
    name: string;
    delay: {
      value?: number;
      unit?: "minutes" | "day" | "month";
    };
    /** Jumlah pesan di langkah ini */
    messages?: number;
  }>;
  created_at: string;
};

export type EnrollmentStatus = "active" | "completed" | "stopped";

export type EnrollRequest = {
  /** Kontak; dicari, atau DIBUAT bila belum ada dan penandanya `phone_number`/`email` (profil hanya untuk kontak baru). Identitas channel harus sudah dikenal. */
  contact: ContactRef;
  /** ISO 8601 dengan zona waktu; null = sekarang. Langkah pertama dijadwalkan sesuai jedanya dari waktu ini. */
  start_at?: string | null;
  client_reference?: string | null;
};

export type Enrollment = {
  followup_id: string;
  contact_id: string;
  status: EnrollmentStatus;
  current_step: number | null;
  next_run_at: string | null;
  started_at: string;
  stopped_at: string | null;
  stop_reason: string | null;
  client_reference: string | null;
};

export type WebhookEventType = "message.received" | "message.status_updated" | "contact.created" | "contact.updated" | "lead.created" | "lead.stage_changed" | "deal.won" | "deal.lost" | "broadcast.completed" | "followup.enrollment.completed" | "followup.enrollment.stopped" | "webchat.visitor.identified" | "webhook.test";

export type WebhookInput = {
  /** Wajib https dan alamat publik (IP privat, localhost, `::ffff:`, CGNAT, dan hasil DNS ke alamat privat ditolak). Diperiksa lagi saat kirim. */
  url: string;
  /** Duplikat dibuang. `webhook.test` hanya dikirim lewat `POST /webhooks/{id}/test`. */
  events: Array<WebhookSubscribableEvent>;
  description?: string | null;
};

export type WebhookSubscription = {
  id: string;
  url: string;
  events: Array<WebhookEventType>;
  description: string | null;
  enabled: boolean;
  disabled_reason?: string | null;
  last_delivery: {
    at?: string;
    response_status?: number | null;
    succeeded?: boolean;
  } | null;
  created_at: string;
};

export type WebhookEvent = {
  id: string;
  type: WebhookEventType;
  api_version: "v3";
  created_at: string;
  /** Objek sumber daya, bentuknya sama dengan GET di API (Message, Contact, Lead, Broadcast, Enrollment, DealEvent, WebchatVisitorIdentified). */
  data: Record<string, unknown>;
};

export type EndpointSummary = {
  id: string;
  channel: ChannelName;
  transport: string | null;
  label: string;
  status: "ready" | "not_ready" | "disconnected";
};

export type BroadcastRecipientStatus = "pending" | "sent" | "delivered" | "read" | "failed" | "cancelled";

export type RejectedRecipient = {
  contact_id: string | null;
  phone_number: string | null;
  code: string;
  message: string;
};

export type WebhookSubscribableEvent = "message.received" | "message.status_updated" | "contact.created" | "contact.updated" | "lead.created" | "lead.stage_changed" | "deal.won" | "deal.lost" | "broadcast.completed" | "followup.enrollment.completed" | "followup.enrollment.stopped" | "webchat.visitor.identified";

/** Data event `deal.won` / `deal.lost` (v3.0 belum punya endpoint deal). */
export type DealEvent = {
  id?: string;
  title?: string | null;
  contact_id?: string | null;
  pipeline_id?: string | null;
  stage?: {
    id?: string;
    name?: string | null;
  } | null;
  status?: string | null;
  value?: number | null;
  currency?: string | null;
  occurred_at?: string | null;
};

/** SATU cara menyebut "siapa" di seluruh API v3 (pesan, lead, followup). Penanda utama: `id` (kontak yang sudah ada; tak boleh digabung field lain), atau `phone_number`, atau `channel` + `external_id`, atau `email` — bila beberapa diisi, dipilih berurutan `phone_number` → `channel`/`external_id` → `email`. Field profil (`name`, `email`, `company_name`, `tags`, `lifecycle_stage`, `locale`, `timezone`) HANYA mengisi kontak yang BARU dibuat; kontak yang sudah ada tidak diubah (pakai `POST /contacts/upsert`). Kueri `GET /messages/send-options` & `GET /contacts/lookup` memakai kunci yang sama secara datar (`contact_id` untuk `id`). Bentuk salah → `422 validation_failed` dengan `fields`. */
export type ContactRef = {
  /** Id kontak yang sudah ada (kontak gabungan diikuti ke tujuannya). */
  id?: string;
  /** Nomor WhatsApp, format bebas (`0812…`, `62812…`, `+62 812…`). */
  phone_number?: string;
  channel?: ChannelName;
  /** Id pelanggan di channel (IGSID/PSID; untuk WhatsApp = nomor). Berpasangan dengan `channel`. */
  external_id?: string;
  email?: string;
  name?: string | null;
  company_name?: string | null;
  tags?: Array<string>;
  lifecycle_stage?: string | null;
  locale?: string | null;
  timezone?: string | null;
};

/** Data event `webchat.visitor.identified`: pengunjung widget website naik tingkat kepercayaan — mengisi formulir perkenalan (`contact_provided`) atau terbukti lewat login website / kode email (`verified`). Tanpa email/nama: ambil lewat `GET /contacts/{contact_id}`. */
export type WebchatVisitorIdentified = {
  /** Id pengunjung (`vis_…`) — juga `external_id` identitas webchat kontak. */
  visitor_id: string;
  channel: "webchat";
  /** Id widget (`wgt_…`). */
  endpoint_id: string;
  /** Kontak pengunjung; null bila pengunjung belum pernah menulis/mengisi formulir. */
  contact_id?: string | null;
  trust_level: "contact_provided" | "verified";
  method: "prechat" | "identity_token" | "otp_email";
  /** `sub` identity token (pengguna website) bila `method: identity_token`. */
  external_user_id?: string | null;
  /** Domain email pengunjung (mis. `perusahaan.co.id`) untuk segmentasi B2B; alamat lengkap tak dikirim. */
  email_domain?: string | null;
  identified_at: string;
};

/** Query, body, `data` dan `meta` respons sukses per operasi (operationId OpenAPI). */
export interface Operations {
  /** GET /me */
  getMe: {
    query: Record<string, never>;
    body: never;
    data: Me;
    meta: Meta;
  };
  /** GET /channels */
  listChannels: {
    query: Record<string, never>;
    body: never;
    data: Array<Channel>;
    meta: Meta;
  };
  /** GET /endpoints */
  listEndpoints: {
    query: {
      channel?: ChannelName;
      transport?: Transport;
      /** Hanya yang siap mengirim */
      ready?: boolean;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<Endpoint>;
    item: Endpoint;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** GET /endpoints/{endpoint_id} */
  getEndpoint: {
    query: Record<string, never>;
    body: never;
    data: Endpoint;
    meta: Meta;
  };
  /** GET /endpoints/{endpoint_id}/templates */
  listEndpointTemplates: {
    query: {
      category?: "marketing" | "utility" | "authentication";
      search?: string;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<Template>;
    item: Template;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** GET /template-variables */
  listTemplateVariables: {
    query: Record<string, never>;
    body: never;
    data: Array<TemplateVariable>;
    meta: Meta;
  };
  /** GET /messages/send-options */
  getMessageSendOptions: {
    query: {
      contact_id?: string;
      phone_number?: string;
      channel?: ChannelName;
      external_id?: string;
      /** Kontak yang sudah ada dengan email ini (tak ada → `contact: null`, `options: []`). */
      email?: string;
      /** Akun tempat identitas Instagram/Messenger dikenal (bersama `channel` + `external_id`). */
      endpoint_id?: string;
    };
    body: never;
    data: SendOptions;
    meta: Meta;
  };
  /** POST /messages */
  sendMessage: {
    query: Record<string, never>;
    body: SendMessageRequest;
    data: Message;
    meta: Meta & {
      created: true;
      conversation_created: boolean;
      contact_created: boolean;
    };
  };
  /** GET /messages/{message_id} */
  getMessage: {
    query: Record<string, never>;
    body: never;
    data: Message;
    meta: Meta;
  };
  /** GET /contacts */
  listContacts: {
    query: {
      /** Nama, nomor, email, atau perusahaan (maks. 100 karakter). */
      search?: string;
      group_id?: string;
      /** Satu tag. */
      tag?: string;
      /** Hanya kontak yang berubah sejak waktu ini (ISO 8601). Urutan menjadi `updated_at` naik — untuk sinkron bertahap; tanpa parameter ini urutannya terbaru dibuat. */
      updated_from?: string;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<Contact>;
    item: Contact;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** POST /contacts */
  createContact: {
    query: Record<string, never>;
    body: ContactInput;
    data: Contact;
    meta: Meta;
  };
  /** POST /contacts/upsert */
  upsertContact: {
    query: Record<string, never>;
    body: ContactInput & {
      match_on?: "phone_number" | "email";
    };
    data: Contact;
    meta: Meta & {
      created: boolean;
      redirected_from?: string | null;
    };
  };
  /** GET /contacts/lookup */
  lookupContact: {
    query: {
      phone_number?: string;
      email?: string;
      channel?: ChannelName;
      external_id?: string;
      /** Akun tempat identitas Instagram/Messenger dikenal (bersama `channel` + `external_id`). */
      endpoint_id?: string;
    };
    body: never;
    data: Contact;
    meta: Meta & {
      redirected_from?: string | null;
      matched_by?: "phone_number" | "email" | "identity";
    };
  };
  /** GET /contacts/{contact_id} */
  getContact: {
    query: Record<string, never>;
    body: never;
    data: Contact;
    meta: Meta & {
      redirected_from?: string | null;
    };
  };
  /** PATCH /contacts/{contact_id} */
  updateContact: {
    query: Record<string, never>;
    body: ContactPatch;
    data: Contact;
    meta: Meta & {
      redirected_from?: string | null;
    };
  };
  /** GET /contact-groups */
  listContactGroups: {
    query: {
      search?: string;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<ContactGroup>;
    item: ContactGroup;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** GET /pipelines */
  listPipelines: {
    query: Record<string, never>;
    body: never;
    data: Array<Pipeline>;
    meta: Meta;
  };
  /** POST /leads */
  createLead: {
    query: Record<string, never>;
    body: CreateLeadRequest;
    data: Lead;
    meta: Meta & {
      created: boolean;
      contact_created: boolean;
    };
  };
  /** GET /leads/{lead_id} */
  getLead: {
    query: Record<string, never>;
    body: never;
    data: Lead;
    meta: Meta;
  };
  /** GET /broadcasts */
  listBroadcasts: {
    query: {
      status?: BroadcastStatus;
      endpoint_id?: string;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<Broadcast>;
    item: Broadcast;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** POST /broadcasts */
  createBroadcast: {
    query: Record<string, never>;
    body: CreateBroadcastRequest;
    data: Broadcast;
    meta: Meta & {
      accepted_recipients: number;
      rejected_count: number;
      rejected_recipients: Array<RejectedRecipient>;
    };
  };
  /** GET /broadcasts/{broadcast_id} */
  getBroadcast: {
    query: Record<string, never>;
    body: never;
    data: Broadcast;
    meta: Meta;
  };
  /** GET /broadcasts/{broadcast_id}/recipients */
  listBroadcastRecipients: {
    query: {
      status?: BroadcastRecipientStatus;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<BroadcastRecipient>;
    item: BroadcastRecipient;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** POST /broadcasts/{broadcast_id}/cancel */
  cancelBroadcast: {
    query: Record<string, never>;
    body: never;
    data: Broadcast;
    meta: Meta;
  };
  /** GET /followups */
  listFollowups: {
    query: {
      status?: "draft" | "active" | "stopped";
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<Followup>;
    item: Followup;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** GET /followups/{followup_id} */
  getFollowup: {
    query: Record<string, never>;
    body: never;
    data: Followup;
    meta: Meta;
  };
  /** GET /followups/{followup_id}/enrollments */
  listFollowupEnrollments: {
    query: {
      status?: EnrollmentStatus;
      contact_id?: string;
      page?: number;
      per_page?: number;
    };
    body: never;
    data: Array<Enrollment>;
    item: Enrollment;
    meta: Meta & {
      pagination: Pagination;
    };
  };
  /** POST /followups/{followup_id}/enrollments */
  enrollFollowup: {
    query: Record<string, never>;
    body: EnrollRequest;
    data: Enrollment;
    meta: Meta & {
      created: boolean;
      contact_created: boolean;
    };
  };
  /** DELETE /followups/{followup_id}/enrollments/{contact_id} */
  unenrollFollowup: {
    query: Record<string, never>;
    body: never;
    data: Enrollment;
    meta: Meta & {
      stopped_messages?: number;
    };
  };
  /** GET /webhooks */
  listWebhooks: {
    query: Record<string, never>;
    body: never;
    data: Array<WebhookSubscription>;
    meta: Meta;
  };
  /** POST /webhooks */
  createWebhook: {
    query: Record<string, never>;
    body: WebhookInput;
    data: WebhookSubscription & {
      signing_secret: string;
    };
    meta: Meta;
  };
  /** PATCH /webhooks/{webhook_id} */
  updateWebhook: {
    query: Record<string, never>;
    body: {
      url?: string;
      events?: Array<WebhookSubscribableEvent>;
      description?: string | null;
      /** `true` pada langganan yang dinonaktifkan otomatis menghidupkannya lagi dari nol. */
      enabled?: boolean;
    };
    data: WebhookSubscription;
    meta: Meta;
  };
  /** DELETE /webhooks/{webhook_id} */
  deleteWebhook: {
    query: Record<string, never>;
    body: never;
    data: {
      id?: string;
      deleted?: true;
    };
    meta: Meta;
  };
  /** POST /webhooks/{webhook_id}/test */
  testWebhook: {
    query: Record<string, never>;
    body: never;
    data: {
      delivered: boolean;
      response_status: number | null;
      duration_ms: number;
      error?: string | null;
    };
    meta: Meta;
  };
  /** POST /webhooks/{webhook_id}/rotate-secret */
  rotateWebhookSecret: {
    query: Record<string, never>;
    body: never;
    data: {
      signing_secret: string;
      previous_secret_expires_at: string;
    };
    meta: Meta;
  };
}
