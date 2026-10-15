# Dibangkitkan dari openapi/openapi-v3.json oleh scripts/generate-types.mjs — jangan diubah manual.
# API 3.0.0-rc.2
"""Tipe API v3 (TypedDict) — format kabel (snake_case), sama dengan `ApiResponse.data`."""

from __future__ import annotations

from typing import TYPE_CHECKING, Any, Dict, List, Literal, Optional, TypedDict, Union

if TYPE_CHECKING:
    from .content import TemplateContent


class Meta(TypedDict):
    request_id: str


class Pagination(TypedDict):
    page: int
    per_page: int
    total: int
    total_pages: int
    has_next_page: bool
    has_prev_page: bool


class ErrorObjectFieldsItem(TypedDict):
    field: str
    code: str
    message: str


class ErrorObject(TypedDict):
    #: Kode mesin yang stabil — bercabanglah dengan ini. Katalog lengkap (status HTTP & arti) ada di `components.x-error-codes`; kode baru bisa bertambah.
    code: str
    type: Literal["invalid_request", "authentication_error", "permission_error", "not_found", "conflict", "rate_limit_error", "api_error", "channel_error", "service_unavailable"]
    details: Optional[Dict[str, Any]]
    fields: List[ErrorObjectFieldsItem]
    doc_url: str


class MeTenant(TypedDict):
    id: str
    name: str


class _MeApiKeyRequired(TypedDict):
    #: 6 karakter awal untuk identifikasi
    prefix: str
    status: Literal["active"]


class MeApiKey(_MeApiKeyRequired, total=False):
    approved_at: Optional[str]


class MeQuota(TypedDict):
    #: null = tak terbatas
    daily_limit: Optional[int]
    used_today: int
    resets_at: str
    per_minute_limit: int


class MeWebhooks(TypedDict, total=False):
    count: int


class Me(TypedDict):
    tenant: MeTenant
    api_key: MeApiKey
    scopes: List[Scope]
    quota: MeQuota
    webhooks: MeWebhooks


class Channel(TypedDict):
    name: ChannelName
    label: str
    transports: List[Transport]
    #: Termasuk paket langganan tenant
    entitled: bool
    #: Channel benar-benar bisa dipakai mengirim saat ini
    operational: bool


class EndpointCapabilities(TypedDict):
    #: Bisnis boleh memulai percakapan: WhatsApp resmi (dengan template) & WhatsApp perangkat
    can_initiate: bool
    requires_template_outside_window: bool
    content_types: List[ContentType]
    #: Bisa dipakai `POST /broadcasts`. v3.0: hanya WhatsApp resmi.
    broadcast: bool


class Endpoint(TypedDict):
    #: endpoint_id
    id: str
    channel: ChannelName
    #: null untuk channel tanpa transport (Instagram, Messenger)
    transport: Optional[Transport]
    #: Nama tampilan; bisa diganti tenant di dashboard
    label: str
    #: Alamat asli: nomor E.164 (WhatsApp) atau @username (Instagram) — tidak ikut berubah bila label diganti
    address: Optional[str]
    status: Literal["ready", "not_ready", "disconnected"]
    capabilities: EndpointCapabilities
    #: Nama terverifikasi/akun di channel
    name: Optional[str]
    #: Status mentah dari penyedia (mis. connected, active, disconnected)
    status_detail: Optional[str]
    created_at: Optional[str]


class _TemplateParametersItemRequired(TypedDict):
    param: str
    component: Literal["header", "body", "button"]


class TemplateParametersItem(_TemplateParametersItemRequired, total=False):
    example: Optional[str]


class TemplateHeader(TypedDict, total=False):
    """Format header; media/lokasi bukan variabel"""

    format: Literal["text", "image", "video", "document", "location"]


class Template(TypedDict):
    #: Dipakai sebagai content.template.template_id
    id: str
    #: Id template di Meta; juga diterima sebagai template_id
    meta_template_id: Optional[str]
    name: str
    language: Optional[str]
    category: Literal["marketing", "utility", "authentication", None]
    status: Literal["approved"]
    #: Teks body dengan placeholder
    preview: Optional[str]
    #: Variabel yang wajib dipetakan di content.template.variables (kunci param persis: named, header.N, button.N)
    parameters: List[TemplateParametersItem]
    #: Format header; media/lokasi bukan variabel
    header: Optional[TemplateHeader]
    parameter_format: Literal["named", "positional"]


class _TemplateVariableRequired(TypedDict):
    key: str
    placeholder: str
    label: str


class TemplateVariable(_TemplateVariableRequired, total=False):
    example: Optional[str]


class TemplateVariableValue(TypedDict):
    #: Nama/nomor parameter template
    param: str
    #: `dynamic` = placeholder global (mis. `{{full_name}}`) diisi saat kirim; `custom` = nilai apa adanya.
    type: Literal["dynamic", "custom"]
    value: str


class _ContentTextTextRequired(TypedDict):
    body: str


class ContentTextText(_ContentTextTextRequired, total=False):
    preview_url: bool


class ContentText(TypedDict):
    type: Literal["text"]
    text: ContentTextText


class _ContentMediaMediaRequired(TypedDict):
    #: URL https yang bisa diunduh publik
    url: str


class ContentMediaMedia(_ContentMediaMediaRequired, total=False):
    """URL diunduh oleh channel/server — wajib alamat publik (alamat privat/lokal ditolak `media_url_rejected`)."""

    caption: Optional[str]
    file_name: Optional[str]
    mime_type: Optional[str]


class ContentMedia(TypedDict):
    type: Literal["image", "video", "audio", "document"]
    #: URL diunduh oleh channel/server — wajib alamat publik (alamat privat/lokal ditolak `media_url_rejected`).
    media: ContentMediaMedia


class _ContentTemplateTemplateLocationRequired(TypedDict):
    latitude: float
    longitude: float


class ContentTemplateTemplateLocation(_ContentTemplateTemplateLocationRequired, total=False):
    """Wajib hanya untuk template berheader LOKASI. Header gambar/video/dokumen memakai media template yang disetujui."""

    name: Optional[str]
    address: Optional[str]


class _ContentTemplateTemplateRequired(TypedDict):
    template_id: str


class ContentTemplateTemplate(_ContentTemplateTemplateRequired, total=False):
    variables: List[TemplateVariableValue]
    #: Wajib hanya untuk template berheader LOKASI. Header gambar/video/dokumen memakai media template yang disetujui.
    location: Optional[ContentTemplateTemplateLocation]


class ContentTemplate(TypedDict):
    type: Literal["template"]
    template: ContentTemplateTemplate


class _SendMessageRequestRequired(TypedDict):
    endpoint_id: str
    #: Penerima. Instagram/Messenger: `channel` + `external_id` (pelanggan harus pernah menghubungi akun ini). `email` = kontak yang sudah ada. Kontak BARU hanya dibuat untuk WhatsApp (dari `phone_number`), memakai field profil.
    contact: ContactRef
    content: Union[Content, TemplateContent]


class SendMessageRequest(_SendMessageRequestRequired, total=False):
    #: Rujukan bebas dari klien (mis. id pesanan); dikembalikan di `Message.client_reference`.
    client_reference: Optional[str]


class SendOptionsContactId(TypedDict):
    id: str
    name: Optional[str]
    #: E.164
    phone_number: Optional[str]


class SendOptionsOptionsItem(TypedDict):
    endpoint: EndpointSummary
    #: Ada format pesan yang sah ke penerima ini lewat titik kirim ini.
    can_send: bool
    #: Hanya template yang sah (mis. di luar jendela 24 jam WhatsApp resmi).
    requires_template: bool
    content_types: List[str]
    reason: Optional[str]
    reason_label: Optional[str]
    existing_conversation_id: Optional[str]


class SendOptions(TypedDict):
    #: Null bila penerima belum terdaftar sebagai kontak.
    contact: Optional[SendOptionsContactId]
    options: List[SendOptionsOptionsItem]


class MessageError(TypedDict, total=False):
    code: str
    message: str


class Message(TypedDict):
    id: str
    conversation_id: str
    contact_id: Optional[str]
    endpoint_id: Optional[str]
    channel: ChannelName
    direction: Literal["inbound", "outbound"]
    #: Bentuk sama dengan `Content`. Template diringkas: `template_id`, `name`, `language`, `category`, `preview`. Jenis lain (lokasi, interaktif, …) dikirim apa adanya di bawah kuncinya.
    content: Dict[str, Any]
    status: MessageStatus
    error: Optional[MessageError]
    client_reference: Optional[str]
    created_at: str
    #: Waktu perubahan status terakhir yang tercatat.
    timestamp: Optional[str]


class ContactInput(TypedDict, total=False):
    """Minimal salah satu dari phone_number atau email."""

    name: Optional[str]
    phone_number: Optional[str]
    #: Disimpan huruf kecil
    email: Optional[str]
    company_name: Optional[str]
    tags: List[str]
    group_ids: List[str]
    lifecycle_stage: Optional[str]
    locale: Optional[str]
    timezone: Optional[str]
    #: Digabung per kunci; nilai null menghapus kunci. Kunci yang punya definisi field kustom divalidasi tipenya (`invalid_custom_field`).
    custom_fields: Dict[str, Any]


class ContactPatch(TypedDict, total=False):
    """Hanya field yang dikirim yang diubah. `tags` & `group_ids` berbentuk `{add, remove}` (tak pernah menimpa seluruh daftar)."""

    name: Optional[str]
    #: Dinormalisasi; nomor milik kontak lain → `identity_taken`
    phone_number: str
    email: Optional[str]
    company_name: Optional[str]
    lifecycle_stage: Optional[str]
    #: Digabung per kunci; nilai null menghapus kunci
    custom_fields: Dict[str, Any]
    tags: AddRemove
    group_ids: AddRemove
    locale: Optional[str]
    timezone: Optional[str]


class AddRemove(TypedDict, total=False):
    add: List[str]
    remove: List[str]


class Contact(TypedDict):
    id: str
    name: Optional[str]
    #: E.164
    phone_number: Optional[str]
    email: Optional[str]
    company_name: Optional[str]
    tags: List[str]
    group_ids: List[str]
    lifecycle_stage: Optional[str]
    custom_fields: Dict[str, Any]
    source: str
    created_at: str
    updated_at: str
    locale: Optional[str]
    timezone: Optional[str]


class ContactGroup(TypedDict):
    id: str
    name: str
    total_contacts: int


class PipelineStagesItem(TypedDict):
    id: str
    name: str
    order: int
    kind: Literal["open", "won", "lost"]


class Pipeline(TypedDict):
    id: str
    name: str
    is_default: bool
    stages: List[PipelineStagesItem]


class _CreateLeadRequestRequired(TypedDict):
    #: Kontak; dicari, atau DIBUAT bila belum ada dan penandanya `phone_number`/`email` (profil hanya untuk kontak baru). Identitas channel harus sudah dikenal.
    contact: ContactRef
    pipeline_id: str


class CreateLeadRequest(_CreateLeadRequestRequired, total=False):
    #: Bawaan = stage pertama
    stage_id: Optional[str]
    score: Optional[int]
    tags: List[str]
    #: Catatan pertama lead.
    note: Optional[str]
    #: Asal lead, mis. wordpress:cf7:12
    source_detail: Optional[str]


class LeadStage(TypedDict):
    id: str
    name: str


class Lead(TypedDict):
    id: str
    contact_id: str
    pipeline_id: str
    stage: LeadStage
    status: Literal["open", "converted", "lost"]
    score: Optional[int]
    tags: List[str]
    source: str
    source_detail: Optional[str]
    created_at: str
    updated_at: str


class _CreateBroadcastRequestContentTemplateLocationRequired(TypedDict):
    latitude: float
    longitude: float


class CreateBroadcastRequestContentTemplateLocation(_CreateBroadcastRequestContentTemplateLocationRequired, total=False):
    """Wajib hanya untuk template berheader LOKASI. Header gambar/video/dokumen memakai media template yang disetujui."""

    name: Optional[str]
    address: Optional[str]


class _CreateBroadcastRequestContentTemplateRequired(TypedDict):
    template_id: str


class CreateBroadcastRequestContentTemplate(_CreateBroadcastRequestContentTemplateRequired, total=False):
    variables: List[TemplateVariableValue]
    #: Wajib hanya untuk template berheader LOKASI. Header gambar/video/dokumen memakai media template yang disetujui.
    location: Optional[CreateBroadcastRequestContentTemplateLocation]


class CreateBroadcastRequestContent(TypedDict):
    """Broadcast WhatsApp resmi wajib template."""

    type: Literal["template"]
    template: CreateBroadcastRequestContentTemplate


class _CreateBroadcastRequestAudienceRecipientsItemRequired(TypedDict):
    phone_number: str


class CreateBroadcastRequestAudienceRecipientsItem(_CreateBroadcastRequestAudienceRecipientsItemRequired, total=False):
    name: Optional[str]


class CreateBroadcastRequestAudience(TypedDict, total=False):
    """TEPAT SATU dari contact_ids, group_ids, recipients. Maks. 1000 penerima setelah diurai (sama dengan kuota harian integrasi)."""

    contact_ids: List[str]
    #: Anggota grup diurai saat permintaan diterima.
    group_ids: List[str]
    recipients: List[CreateBroadcastRequestAudienceRecipientsItem]


class _CreateBroadcastRequestRequired(TypedDict):
    name: str
    #: Titik kirim WhatsApp resmi (`phone_number_id`). v3.0 hanya broadcast resmi.
    endpoint_id: str
    #: Broadcast WhatsApp resmi wajib template.
    content: Union[CreateBroadcastRequestContent, TemplateContent]
    #: TEPAT SATU dari contact_ids, group_ids, recipients. Maks. 1000 penerima setelah diurai (sama dengan kuota harian integrasi).
    audience: CreateBroadcastRequestAudience


class CreateBroadcastRequest(_CreateBroadcastRequestRequired, total=False):
    description: Optional[str]
    #: ISO 8601 dengan zona waktu; null = sekarang.
    scheduled_at: Optional[str]


class BroadcastSummary(TypedDict):
    """Hitungan penerima per status publik (antrean/jeda = pending, ditolak = failed)."""

    total: int
    pending: int
    sent: int
    delivered: int
    read: int
    failed: int
    cancelled: int


class Broadcast(TypedDict):
    id: str
    name: str
    description: Optional[str]
    endpoint_id: str
    channel: ChannelName
    transport: Transport
    status: BroadcastStatus
    scheduled_at: str
    #: Hitungan penerima per status publik (antrean/jeda = pending, ditolak = failed).
    summary: BroadcastSummary
    created_at: str
    completed_at: Optional[str]


class BroadcastRecipientError(TypedDict, total=False):
    code: str
    message: str


class BroadcastRecipient(TypedDict):
    contact_id: Optional[str]
    phone_number: str
    status: BroadcastRecipientStatus
    message_id: Optional[str]
    error: Optional[BroadcastRecipientError]
    updated_at: Optional[str]


class FollowupEndpoint(TypedDict):
    id: Optional[str]
    channel: ChannelName
    transport: Optional[str]


class FollowupStepsItemDelay(TypedDict, total=False):
    value: int
    unit: Literal["minutes", "day", "month"]


class _FollowupStepsItemRequired(TypedDict):
    order: int
    name: str
    delay: FollowupStepsItemDelay


class FollowupStepsItem(_FollowupStepsItemRequired, total=False):
    #: Jumlah pesan di langkah ini
    messages: int


class _FollowupRequired(TypedDict):
    id: str
    title: str
    #: draft = belum aktif; active = menerima pendaftaran; stopped = dijeda/dibatalkan di dashboard.
    status: Literal["draft", "active", "stopped"]
    endpoint: FollowupEndpoint
    steps: List[FollowupStepsItem]
    created_at: str


class Followup(_FollowupRequired, total=False):
    category: Optional[str]


class _EnrollRequestRequired(TypedDict):
    #: Kontak; dicari, atau DIBUAT bila belum ada dan penandanya `phone_number`/`email` (profil hanya untuk kontak baru). Identitas channel harus sudah dikenal.
    contact: ContactRef


class EnrollRequest(_EnrollRequestRequired, total=False):
    #: ISO 8601 dengan zona waktu; null = sekarang. Langkah pertama dijadwalkan sesuai jedanya dari waktu ini.
    start_at: Optional[str]
    client_reference: Optional[str]


class Enrollment(TypedDict):
    followup_id: str
    contact_id: str
    status: EnrollmentStatus
    current_step: Optional[int]
    next_run_at: Optional[str]
    started_at: str
    stopped_at: Optional[str]
    stop_reason: Optional[str]
    client_reference: Optional[str]


class _WebhookInputRequired(TypedDict):
    #: Wajib https dan alamat publik (IP privat, localhost, `::ffff:`, CGNAT, dan hasil DNS ke alamat privat ditolak). Diperiksa lagi saat kirim.
    url: str
    #: Duplikat dibuang. `webhook.test` hanya dikirim lewat `POST /webhooks/{id}/test`.
    events: List[WebhookSubscribableEvent]


class WebhookInput(_WebhookInputRequired, total=False):
    description: Optional[str]


class WebhookSubscriptionLastDelivery(TypedDict, total=False):
    at: str
    response_status: Optional[int]
    succeeded: bool


class _WebhookSubscriptionRequired(TypedDict):
    id: str
    url: str
    events: List[WebhookEventType]
    description: Optional[str]
    enabled: bool
    last_delivery: Optional[WebhookSubscriptionLastDelivery]
    created_at: str


class WebhookSubscription(_WebhookSubscriptionRequired, total=False):
    disabled_reason: Optional[str]


class WebhookEvent(TypedDict):
    id: str
    type: WebhookEventType
    api_version: Literal["v3"]
    created_at: str
    #: Objek sumber daya, bentuknya sama dengan GET di API (Message, Contact, Lead, Broadcast, Enrollment, DealEvent, WebchatVisitorIdentified).
    data: Dict[str, Any]


class EndpointSummary(TypedDict):
    id: str
    channel: ChannelName
    transport: Optional[str]
    label: str
    status: Literal["ready", "not_ready", "disconnected"]


class RejectedRecipient(TypedDict):
    contact_id: Optional[str]
    phone_number: Optional[str]
    code: str
    message: str


class DealEventStage(TypedDict, total=False):
    id: str
    name: Optional[str]


class DealEvent(TypedDict, total=False):
    """Data event `deal.won` / `deal.lost` (v3.0 belum punya endpoint deal)."""

    id: str
    title: Optional[str]
    contact_id: Optional[str]
    pipeline_id: Optional[str]
    stage: Optional[DealEventStage]
    status: Optional[str]
    value: Optional[float]
    currency: Optional[str]
    occurred_at: Optional[str]


class ContactRef(TypedDict, total=False):
    """SATU cara menyebut "siapa" di seluruh API v3 (pesan, lead, followup). Penanda utama: `id` (kontak yang sudah ada; tak boleh digabung field lain), atau `phone_number`, atau `channel` + `external_id`, atau `email` — bila beberapa diisi, dipilih berurutan `phone_number` → `channel`/`external_id` → `email`. Field profil (`name`, `email`, `company_name`, `tags`, `lifecycle_stage`, `locale`, `timezone`) HANYA mengisi kontak yang BARU dibuat; kontak yang sudah ada tidak diubah (pakai `POST /contacts/upsert`). Kueri `GET /messages/send-options` & `GET /contacts/lookup` memakai kunci yang sama secara datar (`contact_id` untuk `id`). Bentuk salah → `422 validation_failed` dengan `fields`."""

    #: Id kontak yang sudah ada (kontak gabungan diikuti ke tujuannya).
    id: str
    #: Nomor WhatsApp, format bebas (`0812…`, `62812…`, `+62 812…`).
    phone_number: str
    channel: ChannelName
    #: Id pelanggan di channel (IGSID/PSID; untuk WhatsApp = nomor). Berpasangan dengan `channel`.
    external_id: str
    email: str
    name: Optional[str]
    company_name: Optional[str]
    tags: List[str]
    lifecycle_stage: Optional[str]
    locale: Optional[str]
    timezone: Optional[str]


class _WebchatVisitorIdentifiedRequired(TypedDict):
    #: Id pengunjung (`vis_…`) — juga `external_id` identitas webchat kontak.
    visitor_id: str
    channel: Literal["webchat"]
    #: Id widget (`wgt_…`).
    endpoint_id: str
    trust_level: Literal["contact_provided", "verified"]
    method: Literal["prechat", "identity_token", "otp_email"]
    identified_at: str


class WebchatVisitorIdentified(_WebchatVisitorIdentifiedRequired, total=False):
    """Data event `webchat.visitor.identified`: pengunjung widget website naik tingkat kepercayaan — mengisi formulir perkenalan (`contact_provided`) atau terbukti lewat login website / kode email (`verified`). Tanpa email/nama: ambil lewat `GET /contacts/{contact_id}`."""

    #: Kontak pengunjung; null bila pengunjung belum pernah menulis/mengisi formulir.
    contact_id: Optional[str]
    #: `sub` identity token (pengguna website) bila `method: identity_token`.
    external_user_id: Optional[str]
    #: Domain email pengunjung (mis. `perusahaan.co.id`) untuk segmentasi B2B; alamat lengkap tak dikirim.
    email_domain: Optional[str]


class GetMeMeta(Meta):
    """meta GET /me"""

    pass


class ListChannelsMeta(Meta):
    """meta GET /channels"""

    pass


class ListEndpointsQuery(TypedDict, total=False):
    """Query GET /endpoints"""

    channel: ChannelName
    transport: Transport
    #: Hanya yang siap mengirim
    ready: bool
    page: int
    per_page: int


class ListEndpointsMeta(Meta):
    """meta GET /endpoints"""

    pagination: Pagination


class GetEndpointMeta(Meta):
    """meta GET /endpoints/{endpoint_id}"""

    pass


class ListEndpointTemplatesQuery(TypedDict, total=False):
    """Query GET /endpoints/{endpoint_id}/templates"""

    category: Literal["marketing", "utility", "authentication"]
    search: str
    page: int
    per_page: int


class ListEndpointTemplatesMeta(Meta):
    """meta GET /endpoints/{endpoint_id}/templates"""

    pagination: Pagination


class ListTemplateVariablesMeta(Meta):
    """meta GET /template-variables"""

    pass


class GetMessageSendOptionsQuery(TypedDict, total=False):
    """Query GET /messages/send-options"""

    contact_id: str
    phone_number: str
    channel: ChannelName
    external_id: str
    #: Kontak yang sudah ada dengan email ini (tak ada → `contact: null`, `options: []`).
    email: str
    #: Akun tempat identitas Instagram/Messenger dikenal (bersama `channel` + `external_id`).
    endpoint_id: str


class GetMessageSendOptionsMeta(Meta):
    """meta GET /messages/send-options"""

    pass


class SendMessageMeta(Meta):
    """meta POST /messages"""

    #: Pesan selalu dibuat (202).
    created: Literal[True]
    conversation_created: bool
    contact_created: bool


class GetMessageMeta(Meta):
    """meta GET /messages/{message_id}"""

    pass


class ListContactsQuery(TypedDict, total=False):
    """Query GET /contacts"""

    #: Nama, nomor, email, atau perusahaan (maks. 100 karakter).
    search: str
    group_id: str
    #: Satu tag.
    tag: str
    #: Hanya kontak yang berubah sejak waktu ini (ISO 8601). Urutan menjadi `updated_at` naik — untuk sinkron bertahap; tanpa parameter ini urutannya terbaru dibuat.
    updated_from: str
    page: int
    per_page: int


class ListContactsMeta(Meta):
    """meta GET /contacts"""

    pagination: Pagination


class CreateContactMeta(Meta):
    """meta POST /contacts"""

    pass


class UpsertContactBody(ContactInput, total=False):
    match_on: Literal["phone_number", "email"]


class _UpsertContactMetaRequired(Meta):
    #: `true` = kontak dibuat (201), `false` = kontak yang ada diperbarui (200).
    created: bool


class UpsertContactMeta(_UpsertContactMetaRequired, total=False):
    """meta POST /contacts/upsert"""

    #: Id kontak sumber gabungan yang diminta, bila kontak itu sudah digabung ke kontak ini.
    redirected_from: Optional[str]


class LookupContactQuery(TypedDict, total=False):
    """Query GET /contacts/lookup"""

    phone_number: str
    email: str
    channel: ChannelName
    external_id: str
    #: Akun tempat identitas Instagram/Messenger dikenal (bersama `channel` + `external_id`).
    endpoint_id: str


class LookupContactMeta(Meta, total=False):
    """meta GET /contacts/lookup"""

    #: Id kontak sumber gabungan yang diminta, bila kontak itu sudah digabung ke kontak ini.
    redirected_from: Optional[str]
    matched_by: Literal["phone_number", "email", "identity"]


class GetContactMeta(Meta, total=False):
    """meta GET /contacts/{contact_id}"""

    #: Id kontak sumber gabungan yang diminta, bila kontak itu sudah digabung ke kontak ini.
    redirected_from: Optional[str]


class UpdateContactMeta(Meta, total=False):
    """meta PATCH /contacts/{contact_id}"""

    #: Id kontak sumber gabungan yang diminta, bila kontak itu sudah digabung ke kontak ini.
    redirected_from: Optional[str]


class ListContactGroupsQuery(TypedDict, total=False):
    """Query GET /contact-groups"""

    search: str
    page: int
    per_page: int


class ListContactGroupsMeta(Meta):
    """meta GET /contact-groups"""

    pagination: Pagination


class ListPipelinesMeta(Meta):
    """meta GET /pipelines"""

    pass


class CreateLeadMeta(Meta):
    """meta POST /leads"""

    #: false = kontak sudah punya lead aktif; lead itu dikembalikan apa adanya
    created: bool
    contact_created: bool


class GetLeadMeta(Meta):
    """meta GET /leads/{lead_id}"""

    pass


class ListBroadcastsQuery(TypedDict, total=False):
    """Query GET /broadcasts"""

    status: BroadcastStatus
    endpoint_id: str
    page: int
    per_page: int


class ListBroadcastsMeta(Meta):
    """meta GET /broadcasts"""

    pagination: Pagination


class CreateBroadcastMeta(Meta):
    """meta POST /broadcasts"""

    accepted_recipients: int
    rejected_count: int
    rejected_recipients: List[RejectedRecipient]


class GetBroadcastMeta(Meta):
    """meta GET /broadcasts/{broadcast_id}"""

    pass


class ListBroadcastRecipientsQuery(TypedDict, total=False):
    """Query GET /broadcasts/{broadcast_id}/recipients"""

    status: BroadcastRecipientStatus
    page: int
    per_page: int


class ListBroadcastRecipientsMeta(Meta):
    """meta GET /broadcasts/{broadcast_id}/recipients"""

    pagination: Pagination


class CancelBroadcastMeta(Meta):
    """meta POST /broadcasts/{broadcast_id}/cancel"""

    pass


class ListFollowupsQuery(TypedDict, total=False):
    """Query GET /followups"""

    status: Literal["draft", "active", "stopped"]
    page: int
    per_page: int


class ListFollowupsMeta(Meta):
    """meta GET /followups"""

    pagination: Pagination


class GetFollowupMeta(Meta):
    """meta GET /followups/{followup_id}"""

    pass


class ListFollowupEnrollmentsQuery(TypedDict, total=False):
    """Query GET /followups/{followup_id}/enrollments"""

    status: EnrollmentStatus
    contact_id: str
    page: int
    per_page: int


class ListFollowupEnrollmentsMeta(Meta):
    """meta GET /followups/{followup_id}/enrollments"""

    pagination: Pagination


class EnrollFollowupMeta(Meta):
    """meta POST /followups/{followup_id}/enrollments"""

    created: bool
    contact_created: bool


class UnenrollFollowupMeta(Meta, total=False):
    """meta DELETE /followups/{followup_id}/enrollments/{contact_id}"""

    stopped_messages: int


class ListWebhooksMeta(Meta):
    """meta GET /webhooks"""

    pass


class CreateWebhookData(WebhookSubscription):
    signing_secret: str


class CreateWebhookMeta(Meta):
    """meta POST /webhooks"""

    pass


class UpdateWebhookBody(TypedDict, total=False):
    url: str
    events: List[WebhookSubscribableEvent]
    description: Optional[str]
    #: `true` pada langganan yang dinonaktifkan otomatis menghidupkannya lagi dari nol.
    enabled: bool


class UpdateWebhookMeta(Meta):
    """meta PATCH /webhooks/{webhook_id}"""

    pass


class DeleteWebhookData(TypedDict, total=False):
    id: str
    deleted: Literal[True]


class DeleteWebhookMeta(Meta):
    """meta DELETE /webhooks/{webhook_id}"""

    pass


class _TestWebhookDataRequired(TypedDict):
    delivered: bool
    response_status: Optional[int]
    duration_ms: int


class TestWebhookData(_TestWebhookDataRequired, total=False):
    error: Optional[str]


class TestWebhookMeta(Meta):
    """meta POST /webhooks/{webhook_id}/test"""

    pass


class RotateWebhookSecretData(TypedDict):
    signing_secret: str
    previous_secret_expires_at: str


class RotateWebhookSecretMeta(Meta):
    """meta POST /webhooks/{webhook_id}/rotate-secret"""

    pass


Scope = Literal["account:read", "channels:read", "messages:read", "messages:write", "contacts:read", "contacts:write", "leads:read", "leads:write", "broadcasts:read", "broadcasts:write", "followups:read", "followups:write", "webhooks:manage"]
ChannelName = Literal["whatsapp", "instagram", "messenger", "webchat"]
Transport = Literal["official", "unofficial"]
ContentType = Literal["text", "image", "video", "audio", "document", "template"]
MessageStatus = Literal["pending", "sent", "delivered", "read", "failed"]
Content = Union[ContentText, ContentMedia, ContentTemplate]
BroadcastStatus = Literal["scheduled", "processing", "paused", "completed", "cancelled", "failed"]
EnrollmentStatus = Literal["active", "completed", "stopped"]
WebhookEventType = Literal["message.received", "message.status_updated", "contact.created", "contact.updated", "lead.created", "lead.stage_changed", "deal.won", "deal.lost", "broadcast.completed", "followup.enrollment.completed", "followup.enrollment.stopped", "webchat.visitor.identified", "webhook.test"]
BroadcastRecipientStatus = Literal["pending", "sent", "delivered", "read", "failed", "cancelled"]
WebhookSubscribableEvent = Literal["message.received", "message.status_updated", "contact.created", "contact.updated", "lead.created", "lead.stage_changed", "deal.won", "deal.lost", "broadcast.completed", "followup.enrollment.completed", "followup.enrollment.stopped", "webchat.visitor.identified"]
GetMeData = Me
ListChannelsData = List[Channel]
ListEndpointsData = List[Endpoint]
ListEndpointsItem = Endpoint
GetEndpointData = Endpoint
ListEndpointTemplatesData = List[Template]
ListEndpointTemplatesItem = Template
ListTemplateVariablesData = List[TemplateVariable]
GetMessageSendOptionsData = SendOptions
SendMessageBody = SendMessageRequest
SendMessageData = Message
GetMessageData = Message
ListContactsData = List[Contact]
ListContactsItem = Contact
CreateContactBody = ContactInput
CreateContactData = Contact
UpsertContactData = Contact
LookupContactData = Contact
GetContactData = Contact
UpdateContactBody = ContactPatch
UpdateContactData = Contact
ListContactGroupsData = List[ContactGroup]
ListContactGroupsItem = ContactGroup
ListPipelinesData = List[Pipeline]
CreateLeadBody = CreateLeadRequest
CreateLeadData = Lead
GetLeadData = Lead
ListBroadcastsData = List[Broadcast]
ListBroadcastsItem = Broadcast
CreateBroadcastBody = CreateBroadcastRequest
CreateBroadcastData = Broadcast
GetBroadcastData = Broadcast
ListBroadcastRecipientsData = List[BroadcastRecipient]
ListBroadcastRecipientsItem = BroadcastRecipient
CancelBroadcastData = Broadcast
ListFollowupsData = List[Followup]
ListFollowupsItem = Followup
GetFollowupData = Followup
ListFollowupEnrollmentsData = List[Enrollment]
ListFollowupEnrollmentsItem = Enrollment
EnrollFollowupBody = EnrollRequest
EnrollFollowupData = Enrollment
UnenrollFollowupData = Enrollment
ListWebhooksData = List[WebhookSubscription]
CreateWebhookBody = WebhookInput
UpdateWebhookData = WebhookSubscription
