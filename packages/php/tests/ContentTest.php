<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Content;
use Plasgos\CrmSdk\Enum\ContentType;

final class ContentTest extends TestCase
{
    public function testText(): void
    {
        self::assertSame(['type' => 'text', 'text' => ['body' => 'Halo', 'preview_url' => false]], Content::text('Halo'));
    }

    public function testMedia(): void
    {
        self::assertSame(
            ['type' => 'document', 'media' => ['url' => 'https://x/inv.pdf', 'caption' => null, 'file_name' => 'inv.pdf', 'mime_type' => null]],
            Content::document('https://x/inv.pdf', 'inv.pdf'),
        );
        self::assertSame('video', Content::media(ContentType::Video, 'https://x/v.mp4')['type']);
    }

    public function testRejectsNonMediaType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Content::media('template', 'https://x');
    }

    public function testTemplateVariablesAreUniquePerParam(): void
    {
        $content = Content::template('tpl')->dynamic('name', '{{full_name}}')->custom('order', 'INV-1')->custom('name', 'Budi')->location(-6.2, 106.8, 'Toko');
        self::assertSame([
            'type' => 'template',
            'template' => [
                'template_id' => 'tpl',
                'variables' => [['param' => 'name', 'type' => 'custom', 'value' => 'Budi'], ['param' => 'order', 'type' => 'custom', 'value' => 'INV-1']],
                'location' => ['latitude' => -6.2, 'longitude' => 106.8, 'name' => 'Toko', 'address' => null],
            ],
        ], $content->toArray());
        self::assertSame(json_encode($content->toArray()), json_encode($content));
    }
}
