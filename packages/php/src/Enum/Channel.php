<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Enum;

/** API v3 channels. Open set: new channels may be added in minor releases. */
enum Channel: string
{
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case Messenger = 'messenger';
}
