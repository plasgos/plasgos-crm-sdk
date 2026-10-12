<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Http\ApiClient;

abstract class AbstractResource
{
    public function __construct(protected readonly ApiClient $http) {}

    /** Join path segments, URL-encoding each id so it can never steer the path. */
    protected static function path(string ...$segments): string
    {
        return '/' . implode('/', array_map('rawurlencode', $segments));
    }
}
