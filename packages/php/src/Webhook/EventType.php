<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Webhook;

/** API v3 webhook event types (`event.type`). Open set. */
final class EventType
{
    public const MESSAGE_RECEIVED = 'message.received';
    public const MESSAGE_STATUS_UPDATED = 'message.status_updated';
    public const CONTACT_CREATED = 'contact.created';
    public const CONTACT_UPDATED = 'contact.updated';
    public const LEAD_CREATED = 'lead.created';
    public const LEAD_STAGE_CHANGED = 'lead.stage_changed';
    public const DEAL_WON = 'deal.won';
    public const DEAL_LOST = 'deal.lost';
    public const BROADCAST_COMPLETED = 'broadcast.completed';
    public const FOLLOWUP_ENROLLMENT_COMPLETED = 'followup.enrollment.completed';
    public const FOLLOWUP_ENROLLMENT_STOPPED = 'followup.enrollment.stopped';
    public const WEBHOOK_TEST = 'webhook.test';
}
