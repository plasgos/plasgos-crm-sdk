<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk;

/**
 * WhatsApp template content. The CRM parses and renders the template; the client only maps
 * variables as `{ param, type, value }`.
 */
final class TemplateContent implements \JsonSerializable
{
    /** @var list<array{param: string, type: 'custom'|'dynamic', value: string}> */
    private array $variables = [];

    /** @var array{latitude: float, longitude: float, name: ?string, address: ?string}|null */
    private ?array $location = null;

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

    /**
     * Required only for templates with a LOCATION header. Image/video/document headers always use
     * the media of the approved template.
     */
    public function location(float $latitude, float $longitude, ?string $name = null, ?string $address = null): self
    {
        $this->location = ['latitude' => $latitude, 'longitude' => $longitude, 'name' => $name, 'address' => $address];
        return $this;
    }

    /** @return array{type: 'template', template: array{template_id: string, variables: list<array{param: string, type: 'custom'|'dynamic', value: string}>, location?: array{latitude: float, longitude: float, name: ?string, address: ?string}}} */
    public function toArray(): array
    {
        $template = ['template_id' => $this->templateId, 'variables' => $this->variables];
        if ($this->location !== null) {
            $template['location'] = $this->location;
        }
        return ['type' => 'template', 'template' => $template];
    }

    /** @return array{type: 'template', template: array{template_id: string, variables: list<array{param: string, type: 'custom'|'dynamic', value: string}>, location?: array{latitude: float, longitude: float, name: ?string, address: ?string}}} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param 'custom'|'dynamic' $type */
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
