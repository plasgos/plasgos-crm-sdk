<?php

// Dibangkitkan dari openapi/openapi-v3.json oleh scripts/generate-types.mjs — jangan diubah manual.
// API 3.0.0-rc.2

declare(strict_types=1);

namespace Plasgos\CrmSdk\Types;

/**
 * Tipe API v3 sebagai alias PHPStan/Psalm: format kabel (snake_case), sama dengan `Response::$data`.
 * Per operasi: `…Query`, `…Body`, `…Data`, `…Item` (daftar) dan `…Meta`.
 * Pakai di docblock kelas Anda: `@phpstan-import-type Contact from \Plasgos\CrmSdk\Types\ApiTypes`.
 *
 * @phpstan-type Meta array{
 *   request_id: string,
 * }
 * @phpstan-type Pagination array{
 *   page: int,
 *   per_page: int,
 *   total: int,
 *   total_pages: int,
 *   has_next_page: bool,
 *   has_prev_page: bool,
 * }
 * @phpstan-type ErrorObject array{
 *   code: string,
 *   type: 'invalid_request'|'authentication_error'|'permission_error'|'not_found'|'conflict'|'rate_limit_error'|'api_error'|'channel_error'|'service_unavailable',
 *   details: array<string, mixed>|null,
 *   fields: list<array{
 *     field: string,
 *     code: string,
 *     message: string,
 *   }>,
 *   doc_url: string,
 * }
 * @phpstan-type Me array{
 *   tenant: array{
 *     id: string,
 *     name: string,
 *   },
 *   api_key: array{
 *     prefix: string,
 *     status: 'active',
 *     approved_at?: string|null,
 *   },
 *   scopes: list<Scope>,
 *   quota: array{
 *     daily_limit: int|null,
 *     used_today: int,
 *     resets_at: string,
 *     per_minute_limit: int,
 *   },
 *   webhooks: array{
 *     count?: int,
 *   },
 * }
 * @phpstan-type Scope 'account:read'|'channels:read'|'messages:read'|'messages:write'|'contacts:read'|'contacts:write'|'leads:read'|'leads:write'|'broadcasts:read'|'broadcasts:write'|'followups:read'|'followups:write'|'webhooks:manage'
 * @phpstan-type ChannelName 'whatsapp'|'instagram'|'messenger'|'webchat'
 * @phpstan-type Transport 'official'|'unofficial'
 * @phpstan-type Channel array{
 *   name: ChannelName,
 *   label: string,
 *   transports: list<Transport>,
 *   entitled: bool,
 *   operational: bool,
 * }
 * @phpstan-type Endpoint array{
 *   id: string,
 *   channel: ChannelName,
 *   transport: Transport|null,
 *   label: string,
 *   address: string|null,
 *   status: 'ready'|'not_ready'|'disconnected',
 *   capabilities: array{
 *     can_initiate: bool,
 *     requires_template_outside_window: bool,
 *     content_types: list<ContentType>,
 *     broadcast: bool,
 *   },
 *   name: string|null,
 *   status_detail: string|null,
 *   created_at: string|null,
 * }
 * @phpstan-type Template array{
 *   id: string,
 *   meta_template_id: string|null,
 *   name: string,
 *   language: string|null,
 *   category: 'marketing'|'utility'|'authentication'|null,
 *   status: 'approved',
 *   preview: string|null,
 *   parameters: list<array{
 *     param: string,
 *     component: 'header'|'body'|'button',
 *     example?: string|null,
 *   }>,
 *   header: array{
 *     format?: 'text'|'image'|'video'|'document'|'location',
 *   }|null,
 *   parameter_format: 'named'|'positional',
 * }
 * @phpstan-type TemplateVariable array{
 *   key: string,
 *   placeholder: string,
 *   label: string,
 *   example?: string|null,
 * }
 * @phpstan-type ContentType 'text'|'image'|'video'|'audio'|'document'|'template'
 * @phpstan-type MessageStatus 'pending'|'sent'|'delivered'|'read'|'failed'
 * @phpstan-type TemplateVariableValue array{
 *   param: string,
 *   type: 'dynamic'|'custom',
 *   value: string,
 * }
 * @phpstan-type Content array{
 *   type: 'text',
 *   text: array{
 *     body: string,
 *     preview_url?: bool,
 *   },
 * }|array{
 *   type: 'image'|'video'|'audio'|'document',
 *   media: array{
 *     url: string,
 *     caption?: string|null,
 *     file_name?: string|null,
 *     mime_type?: string|null,
 *   },
 * }|array{
 *   type: 'template',
 *   template: array{
 *     template_id: string,
 *     variables?: list<TemplateVariableValue>,
 *     location?: array{
 *       latitude: float|int,
 *       longitude: float|int,
 *       name?: string|null,
 *       address?: string|null,
 *     }|null,
 *   },
 * }
 * @phpstan-type SendMessageRequest array{
 *   endpoint_id: string,
 *   contact: ContactRef,
 *   content: Content|\Plasgos\CrmSdk\TemplateContent,
 *   client_reference?: string|null,
 * }
 * @phpstan-type SendOptions array{
 *   contact: null|array{
 *     id: string,
 *     name: string|null,
 *     phone_number: string|null,
 *   },
 *   options: list<array{
 *     endpoint: EndpointSummary,
 *     can_send: bool,
 *     requires_template: bool,
 *     content_types: list<string>,
 *     reason: string|null,
 *     reason_label: string|null,
 *     existing_conversation_id: string|null,
 *   }>,
 * }
 * @phpstan-type Message array{
 *   id: string,
 *   conversation_id: string,
 *   contact_id: string|null,
 *   endpoint_id: string|null,
 *   channel: ChannelName,
 *   direction: 'inbound'|'outbound',
 *   content: array<string, mixed>,
 *   status: MessageStatus,
 *   error: array{
 *     code?: string,
 *     message?: string,
 *   }|null,
 *   client_reference: string|null,
 *   created_at: string,
 *   timestamp: string|null,
 * }
 * @phpstan-type ContactInput array{
 *   name?: string|null,
 *   phone_number?: string|null,
 *   email?: string|null,
 *   company_name?: string|null,
 *   tags?: list<string>,
 *   group_ids?: list<string>,
 *   lifecycle_stage?: string|null,
 *   locale?: string|null,
 *   timezone?: string|null,
 *   custom_fields?: array<string, mixed>,
 * }
 * @phpstan-type ContactPatch array{
 *   name?: string|null,
 *   phone_number?: string,
 *   email?: string|null,
 *   company_name?: string|null,
 *   lifecycle_stage?: string|null,
 *   custom_fields?: array<string, mixed>,
 *   tags?: AddRemove,
 *   group_ids?: AddRemove,
 *   locale?: string|null,
 *   timezone?: string|null,
 * }
 * @phpstan-type AddRemove array{
 *   add?: list<string>,
 *   remove?: list<string>,
 * }
 * @phpstan-type Contact array{
 *   id: string,
 *   name: string|null,
 *   phone_number: string|null,
 *   email: string|null,
 *   company_name: string|null,
 *   tags: list<string>,
 *   group_ids: list<string>,
 *   lifecycle_stage: string|null,
 *   custom_fields: array<string, mixed>,
 *   source: string,
 *   created_at: string,
 *   updated_at: string,
 *   locale: string|null,
 *   timezone: string|null,
 * }
 * @phpstan-type ContactGroup array{
 *   id: string,
 *   name: string,
 *   total_contacts: int,
 * }
 * @phpstan-type Pipeline array{
 *   id: string,
 *   name: string,
 *   is_default: bool,
 *   stages: list<array{
 *     id: string,
 *     name: string,
 *     order: int,
 *     kind: 'open'|'won'|'lost',
 *   }>,
 * }
 * @phpstan-type CreateLeadRequest array{
 *   contact: ContactRef,
 *   pipeline_id: string,
 *   stage_id?: string|null,
 *   score?: int|null,
 *   tags?: list<string>,
 *   note?: string|null,
 *   source_detail?: string|null,
 * }
 * @phpstan-type Lead array{
 *   id: string,
 *   contact_id: string,
 *   pipeline_id: string,
 *   stage: array{
 *     id: string,
 *     name: string,
 *   },
 *   status: 'open'|'converted'|'lost',
 *   score: int|null,
 *   tags: list<string>,
 *   source: string,
 *   source_detail: string|null,
 *   created_at: string,
 *   updated_at: string,
 * }
 * @phpstan-type BroadcastStatus 'scheduled'|'processing'|'paused'|'completed'|'cancelled'|'failed'
 * @phpstan-type CreateBroadcastRequest array{
 *   name: string,
 *   description?: string|null,
 *   endpoint_id: string,
 *   content: array{
 *     type: 'template',
 *     template: array{
 *       template_id: string,
 *       variables?: list<TemplateVariableValue>,
 *       location?: array{
 *         latitude: float|int,
 *         longitude: float|int,
 *         name?: string|null,
 *         address?: string|null,
 *       }|null,
 *     },
 *   }|\Plasgos\CrmSdk\TemplateContent,
 *   audience: array{
 *     contact_ids?: list<string>,
 *     group_ids?: list<string>,
 *     recipients?: list<array{
 *       phone_number: string,
 *       name?: string|null,
 *     }>,
 *   },
 *   scheduled_at?: string|null,
 * }
 * @phpstan-type Broadcast array{
 *   id: string,
 *   name: string,
 *   description: string|null,
 *   endpoint_id: string,
 *   channel: ChannelName,
 *   transport: Transport,
 *   status: BroadcastStatus,
 *   scheduled_at: string,
 *   summary: array{
 *     total: int,
 *     pending: int,
 *     sent: int,
 *     delivered: int,
 *     read: int,
 *     failed: int,
 *     cancelled: int,
 *   },
 *   created_at: string,
 *   completed_at: string|null,
 * }
 * @phpstan-type BroadcastRecipient array{
 *   contact_id: string|null,
 *   phone_number: string,
 *   status: BroadcastRecipientStatus,
 *   message_id: string|null,
 *   error: array{
 *     code?: string,
 *     message?: string,
 *   }|null,
 *   updated_at: string|null,
 * }
 * @phpstan-type Followup array{
 *   id: string,
 *   title: string,
 *   category?: string|null,
 *   status: 'draft'|'active'|'stopped',
 *   endpoint: array{
 *     id: string|null,
 *     channel: ChannelName,
 *     transport: string|null,
 *   },
 *   steps: list<array{
 *     order: int,
 *     name: string,
 *     delay: array{
 *       value?: int,
 *       unit?: 'minutes'|'day'|'month',
 *     },
 *     messages?: int,
 *   }>,
 *   created_at: string,
 * }
 * @phpstan-type EnrollmentStatus 'active'|'completed'|'stopped'
 * @phpstan-type EnrollRequest array{
 *   contact: ContactRef,
 *   start_at?: string|null,
 *   client_reference?: string|null,
 * }
 * @phpstan-type Enrollment array{
 *   followup_id: string,
 *   contact_id: string,
 *   status: EnrollmentStatus,
 *   current_step: int|null,
 *   next_run_at: string|null,
 *   started_at: string,
 *   stopped_at: string|null,
 *   stop_reason: string|null,
 *   client_reference: string|null,
 * }
 * @phpstan-type WebhookEventType 'message.received'|'message.status_updated'|'contact.created'|'contact.updated'|'lead.created'|'lead.stage_changed'|'deal.won'|'deal.lost'|'broadcast.completed'|'followup.enrollment.completed'|'followup.enrollment.stopped'|'webchat.visitor.identified'|'webhook.test'
 * @phpstan-type WebhookInput array{
 *   url: string,
 *   events: list<WebhookSubscribableEvent>,
 *   description?: string|null,
 * }
 * @phpstan-type WebhookSubscription array{
 *   id: string,
 *   url: string,
 *   events: list<WebhookEventType>,
 *   description: string|null,
 *   enabled: bool,
 *   disabled_reason?: string|null,
 *   last_delivery: array{
 *     at?: string,
 *     response_status?: int|null,
 *     succeeded?: bool,
 *   }|null,
 *   created_at: string,
 * }
 * @phpstan-type WebhookEvent array{
 *   id: string,
 *   type: WebhookEventType,
 *   api_version: 'v3',
 *   created_at: string,
 *   data: array<string, mixed>,
 * }
 * @phpstan-type EndpointSummary array{
 *   id: string,
 *   channel: ChannelName,
 *   transport: string|null,
 *   label: string,
 *   status: 'ready'|'not_ready'|'disconnected',
 * }
 * @phpstan-type BroadcastRecipientStatus 'pending'|'sent'|'delivered'|'read'|'failed'|'cancelled'
 * @phpstan-type RejectedRecipient array{
 *   contact_id: string|null,
 *   phone_number: string|null,
 *   code: string,
 *   message: string,
 * }
 * @phpstan-type WebhookSubscribableEvent 'message.received'|'message.status_updated'|'contact.created'|'contact.updated'|'lead.created'|'lead.stage_changed'|'deal.won'|'deal.lost'|'broadcast.completed'|'followup.enrollment.completed'|'followup.enrollment.stopped'|'webchat.visitor.identified'
 * @phpstan-type DealEvent array{
 *   id?: string,
 *   title?: string|null,
 *   contact_id?: string|null,
 *   pipeline_id?: string|null,
 *   stage?: array{
 *     id?: string,
 *     name?: string|null,
 *   }|null,
 *   status?: string|null,
 *   value?: float|int|null,
 *   currency?: string|null,
 *   occurred_at?: string|null,
 * }
 * @phpstan-type ContactRef array{
 *   id?: string,
 *   phone_number?: string,
 *   channel?: ChannelName,
 *   external_id?: string,
 *   email?: string,
 *   name?: string|null,
 *   company_name?: string|null,
 *   tags?: list<string>,
 *   lifecycle_stage?: string|null,
 *   locale?: string|null,
 *   timezone?: string|null,
 * }
 * @phpstan-type WebchatVisitorIdentified array{
 *   visitor_id: string,
 *   channel: 'webchat',
 *   endpoint_id: string,
 *   contact_id?: string|null,
 *   trust_level: 'contact_provided'|'verified',
 *   method: 'prechat'|'identity_token'|'otp_email',
 *   external_user_id?: string|null,
 *   email_domain?: string|null,
 *   identified_at: string,
 * }
 * @phpstan-type GetMeQuery array<string, never>
 * @phpstan-type GetMeData Me
 * @phpstan-type GetMeMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListChannelsQuery array<string, never>
 * @phpstan-type ListChannelsData list<Channel>
 * @phpstan-type ListChannelsMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListEndpointsQuery array{
 *   channel?: ChannelName,
 *   transport?: Transport,
 *   ready?: bool,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListEndpointsData list<Endpoint>
 * @phpstan-type ListEndpointsItem Endpoint
 * @phpstan-type ListEndpointsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type GetEndpointQuery array<string, never>
 * @phpstan-type GetEndpointData Endpoint
 * @phpstan-type GetEndpointMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListEndpointTemplatesQuery array{
 *   category?: 'marketing'|'utility'|'authentication',
 *   search?: string,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListEndpointTemplatesData list<Template>
 * @phpstan-type ListEndpointTemplatesItem Template
 * @phpstan-type ListEndpointTemplatesMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type ListTemplateVariablesQuery array<string, never>
 * @phpstan-type ListTemplateVariablesData list<TemplateVariable>
 * @phpstan-type ListTemplateVariablesMeta array{
 *   request_id: string,
 * }
 * @phpstan-type GetMessageSendOptionsQuery array{
 *   contact_id?: string,
 *   phone_number?: string,
 *   channel?: ChannelName,
 *   external_id?: string,
 *   email?: string,
 *   endpoint_id?: string,
 * }
 * @phpstan-type GetMessageSendOptionsData SendOptions
 * @phpstan-type GetMessageSendOptionsMeta array{
 *   request_id: string,
 * }
 * @phpstan-type SendMessageQuery array<string, never>
 * @phpstan-type SendMessageBody SendMessageRequest
 * @phpstan-type SendMessageData Message
 * @phpstan-type SendMessageMeta array{
 *   request_id: string,
 *   created: true,
 *   conversation_created: bool,
 *   contact_created: bool,
 * }
 * @phpstan-type GetMessageQuery array<string, never>
 * @phpstan-type GetMessageData Message
 * @phpstan-type GetMessageMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListContactsQuery array{
 *   search?: string,
 *   group_id?: string,
 *   tag?: string,
 *   updated_from?: string,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListContactsData list<Contact>
 * @phpstan-type ListContactsItem Contact
 * @phpstan-type ListContactsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type CreateContactQuery array<string, never>
 * @phpstan-type CreateContactBody ContactInput
 * @phpstan-type CreateContactData Contact
 * @phpstan-type CreateContactMeta array{
 *   request_id: string,
 * }
 * @phpstan-type UpsertContactQuery array<string, never>
 * @phpstan-type UpsertContactBody array{
 *   name?: string|null,
 *   phone_number?: string|null,
 *   email?: string|null,
 *   company_name?: string|null,
 *   tags?: list<string>,
 *   group_ids?: list<string>,
 *   lifecycle_stage?: string|null,
 *   locale?: string|null,
 *   timezone?: string|null,
 *   custom_fields?: array<string, mixed>,
 *   match_on?: 'phone_number'|'email',
 * }
 * @phpstan-type UpsertContactData Contact
 * @phpstan-type UpsertContactMeta array{
 *   request_id: string,
 *   created: bool,
 *   redirected_from?: string|null,
 * }
 * @phpstan-type LookupContactQuery array{
 *   phone_number?: string,
 *   email?: string,
 *   channel?: ChannelName,
 *   external_id?: string,
 *   endpoint_id?: string,
 * }
 * @phpstan-type LookupContactData Contact
 * @phpstan-type LookupContactMeta array{
 *   request_id: string,
 *   redirected_from?: string|null,
 *   matched_by?: 'phone_number'|'email'|'identity',
 * }
 * @phpstan-type GetContactQuery array<string, never>
 * @phpstan-type GetContactData Contact
 * @phpstan-type GetContactMeta array{
 *   request_id: string,
 *   redirected_from?: string|null,
 * }
 * @phpstan-type UpdateContactQuery array<string, never>
 * @phpstan-type UpdateContactBody ContactPatch
 * @phpstan-type UpdateContactData Contact
 * @phpstan-type UpdateContactMeta array{
 *   request_id: string,
 *   redirected_from?: string|null,
 * }
 * @phpstan-type ListContactGroupsQuery array{
 *   search?: string,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListContactGroupsData list<ContactGroup>
 * @phpstan-type ListContactGroupsItem ContactGroup
 * @phpstan-type ListContactGroupsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type ListPipelinesQuery array<string, never>
 * @phpstan-type ListPipelinesData list<Pipeline>
 * @phpstan-type ListPipelinesMeta array{
 *   request_id: string,
 * }
 * @phpstan-type CreateLeadQuery array<string, never>
 * @phpstan-type CreateLeadBody CreateLeadRequest
 * @phpstan-type CreateLeadData Lead
 * @phpstan-type CreateLeadMeta array{
 *   request_id: string,
 *   created: bool,
 *   contact_created: bool,
 * }
 * @phpstan-type GetLeadQuery array<string, never>
 * @phpstan-type GetLeadData Lead
 * @phpstan-type GetLeadMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListBroadcastsQuery array{
 *   status?: BroadcastStatus,
 *   endpoint_id?: string,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListBroadcastsData list<Broadcast>
 * @phpstan-type ListBroadcastsItem Broadcast
 * @phpstan-type ListBroadcastsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type CreateBroadcastQuery array<string, never>
 * @phpstan-type CreateBroadcastBody CreateBroadcastRequest
 * @phpstan-type CreateBroadcastData Broadcast
 * @phpstan-type CreateBroadcastMeta array{
 *   request_id: string,
 *   accepted_recipients: int,
 *   rejected_count: int,
 *   rejected_recipients: list<RejectedRecipient>,
 * }
 * @phpstan-type GetBroadcastQuery array<string, never>
 * @phpstan-type GetBroadcastData Broadcast
 * @phpstan-type GetBroadcastMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListBroadcastRecipientsQuery array{
 *   status?: BroadcastRecipientStatus,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListBroadcastRecipientsData list<BroadcastRecipient>
 * @phpstan-type ListBroadcastRecipientsItem BroadcastRecipient
 * @phpstan-type ListBroadcastRecipientsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type CancelBroadcastQuery array<string, never>
 * @phpstan-type CancelBroadcastData Broadcast
 * @phpstan-type CancelBroadcastMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListFollowupsQuery array{
 *   status?: 'draft'|'active'|'stopped',
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListFollowupsData list<Followup>
 * @phpstan-type ListFollowupsItem Followup
 * @phpstan-type ListFollowupsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type GetFollowupQuery array<string, never>
 * @phpstan-type GetFollowupData Followup
 * @phpstan-type GetFollowupMeta array{
 *   request_id: string,
 * }
 * @phpstan-type ListFollowupEnrollmentsQuery array{
 *   status?: EnrollmentStatus,
 *   contact_id?: string,
 *   page?: int,
 *   per_page?: int,
 * }
 * @phpstan-type ListFollowupEnrollmentsData list<Enrollment>
 * @phpstan-type ListFollowupEnrollmentsItem Enrollment
 * @phpstan-type ListFollowupEnrollmentsMeta array{
 *   request_id: string,
 *   pagination: Pagination,
 * }
 * @phpstan-type EnrollFollowupQuery array<string, never>
 * @phpstan-type EnrollFollowupBody EnrollRequest
 * @phpstan-type EnrollFollowupData Enrollment
 * @phpstan-type EnrollFollowupMeta array{
 *   request_id: string,
 *   created: bool,
 *   contact_created: bool,
 * }
 * @phpstan-type UnenrollFollowupQuery array<string, never>
 * @phpstan-type UnenrollFollowupData Enrollment
 * @phpstan-type UnenrollFollowupMeta array{
 *   request_id: string,
 *   stopped_messages?: int,
 * }
 * @phpstan-type ListWebhooksQuery array<string, never>
 * @phpstan-type ListWebhooksData list<WebhookSubscription>
 * @phpstan-type ListWebhooksMeta array{
 *   request_id: string,
 * }
 * @phpstan-type CreateWebhookQuery array<string, never>
 * @phpstan-type CreateWebhookBody WebhookInput
 * @phpstan-type CreateWebhookData array{
 *   id: string,
 *   url: string,
 *   events: list<WebhookEventType>,
 *   description: string|null,
 *   enabled: bool,
 *   disabled_reason?: string|null,
 *   last_delivery: array{
 *     at?: string,
 *     response_status?: int|null,
 *     succeeded?: bool,
 *   }|null,
 *   created_at: string,
 *   signing_secret: string,
 * }
 * @phpstan-type CreateWebhookMeta array{
 *   request_id: string,
 * }
 * @phpstan-type UpdateWebhookQuery array<string, never>
 * @phpstan-type UpdateWebhookBody array{
 *   url?: string,
 *   events?: list<WebhookSubscribableEvent>,
 *   description?: string|null,
 *   enabled?: bool,
 * }
 * @phpstan-type UpdateWebhookData WebhookSubscription
 * @phpstan-type UpdateWebhookMeta array{
 *   request_id: string,
 * }
 * @phpstan-type DeleteWebhookQuery array<string, never>
 * @phpstan-type DeleteWebhookData array{
 *   id?: string,
 *   deleted?: true,
 * }
 * @phpstan-type DeleteWebhookMeta array{
 *   request_id: string,
 * }
 * @phpstan-type TestWebhookQuery array<string, never>
 * @phpstan-type TestWebhookData array{
 *   delivered: bool,
 *   response_status: int|null,
 *   duration_ms: int,
 *   error?: string|null,
 * }
 * @phpstan-type TestWebhookMeta array{
 *   request_id: string,
 * }
 * @phpstan-type RotateWebhookSecretQuery array<string, never>
 * @phpstan-type RotateWebhookSecretData array{
 *   signing_secret: string,
 *   previous_secret_expires_at: string,
 * }
 * @phpstan-type RotateWebhookSecretMeta array{
 *   request_id: string,
 * }
 */
final class ApiTypes
{
    private function __construct()
    {
    }
}
