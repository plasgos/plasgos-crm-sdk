<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Enum;

/** API v3 message content types. */
enum ContentType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Template = 'template';

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Video, self::Audio, self::Document], true);
    }
}
