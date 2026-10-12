<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Enum;

enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    /** Spelling is intentional — it matches the server enum. */
    case Messenger = 'messanger';
    case Instagram = 'instagram';
}
