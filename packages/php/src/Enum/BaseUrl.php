<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Enum;

enum BaseUrl: string
{
    case Production = 'https://wa-client.plasgos.co.id';
    case Sandbox = 'https://api-crm.sandbox.plasgos.co.id';

    /** Accepts a BaseUrl, the strings "production"/"sandbox", or an absolute URL. */
    public static function resolve(BaseUrl|string|null $input): string
    {
        if ($input === null) {
            return self::Production->value;
        }
        if ($input instanceof BaseUrl) {
            return $input->value;
        }
        return match (strtolower($input)) {
            'production', 'prod' => self::Production->value,
            'sandbox', 'staging' => self::Sandbox->value,
            default => rtrim($input, '/'),
        };
    }
}
