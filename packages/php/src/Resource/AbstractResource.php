<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Http\HttpClient;

abstract class AbstractResource
{
    public function __construct(protected readonly HttpClient $http) {}

    /**
     * Recursively drop null values so we never send `"key": null` noise.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function compact(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[$key] = is_array($value) && ! array_is_list($value)
                ? self::compact($value)
                : $value;
        }
        return $out;
    }
}
