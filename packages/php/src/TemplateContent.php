<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * WhatsApp template content. The CRM parses and renders the template; the client only maps
 * variables as `{ param, type, value }`.
 */
final class TemplateContent implements \JsonSerializable
{
    /** @var list<array{param: string, type: string, value: string}> */
    private array $variables = [];

    /** @var array{url: string, file_name: ?string}|null */
    private ?array $headerMedia = null;

    public function __construct(private readonly string $templateId) {}

    /** Global placeholder resolved by the CRM at send time, e.g. `{{full_name}}`. */
    public function dynamic(string $param, string $placeholder): self
    {
        return $this->variable($param, 'dynamic', $placeholder);
    }

    /** Literal value. */
    public function custom(string $param, string $value): self
    {
        return $this->variable($param, 'custom', $value);
    }

    public function headerMedia(string $url, ?string $fileName = null): self
    {
        $this->headerMedia = ['url' => $url, 'file_name' => $fileName];
        return $this;
    }

    /** @return array{type: string, template: array<string, mixed>} */
    public function toArray(): array
    {
        $template = ['template_id' => $this->templateId, 'variables' => $this->variables];
        if ($this->headerMedia !== null) {
            $template['header_media'] = $this->headerMedia;
        }
        return ['type' => 'template', 'template' => $template];
    }

    /** @return array{type: string, template: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function variable(string $param, string $type, string $value): self
    {
        foreach ($this->variables as $i => $existing) {
            if ($existing['param'] === $param) {
                $this->variables[$i] = ['param' => $param, 'type' => $type, 'value' => $value];
                return $this;
            }
        }
        $this->variables[] = ['param' => $param, 'type' => $type, 'value' => $value];
        return $this;
    }
}
