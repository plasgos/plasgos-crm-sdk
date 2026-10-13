<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

use Plasgos\CrmSdk\Enum\ContentType;

/**
 * Builders for message `content` — the same shape as the CRM inbox (docs/SDK_SPEC.md §3.4).
 *
 * ```php
 * Content::text('Halo');
 * Content::image('https://…/promo.jpg', 'Promo');
 * Content::template('66f1…')->dynamic('name', '{{full_name}}')->custom('order', 'INV-1');
 * ```
 */
final class Content
{
    /** @return array{type: 'text', text: array{body: string, preview_url: bool}} */
    public static function text(string $body, bool $previewUrl = false): array
    {
        return ['type' => ContentType::Text->value, 'text' => ['body' => $body, 'preview_url' => $previewUrl]];
    }

    /** @return array{type: 'audio'|'document'|'image'|'video', media: array{url: string, caption: ?string, file_name: ?string, mime_type: ?string}} */
    public static function media(ContentType|string $type, string $url, ?string $caption = null, ?string $fileName = null, ?string $mimeType = null): array
    {
        $value = match ($type instanceof ContentType ? $type : ContentType::tryFrom($type)) {
            ContentType::Image => 'image',
            ContentType::Video => 'video',
            ContentType::Audio => 'audio',
            ContentType::Document => 'document',
            default => throw new \InvalidArgumentException('Content::media type must be one of image, video, audio, document'),
        };

        return [
            'type' => $value,
            'media' => ['url' => $url, 'caption' => $caption, 'file_name' => $fileName, 'mime_type' => $mimeType],
        ];
    }

    /** @return array{type: 'audio'|'document'|'image'|'video', media: array{url: string, caption: ?string, file_name: ?string, mime_type: ?string}} */
    public static function image(string $url, ?string $caption = null): array
    {
        return self::media(ContentType::Image, $url, $caption);
    }

    /** @return array{type: 'audio'|'document'|'image'|'video', media: array{url: string, caption: ?string, file_name: ?string, mime_type: ?string}} */
    public static function video(string $url, ?string $caption = null): array
    {
        return self::media(ContentType::Video, $url, $caption);
    }

    /** @return array{type: 'audio'|'document'|'image'|'video', media: array{url: string, caption: ?string, file_name: ?string, mime_type: ?string}} */
    public static function document(string $url, ?string $fileName = null, ?string $caption = null): array
    {
        return self::media(ContentType::Document, $url, $caption, $fileName);
    }

    public static function template(string $templateId): TemplateContent
    {
        return new TemplateContent($templateId);
    }
}
