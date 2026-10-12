<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Enum;

enum ContentType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
}
