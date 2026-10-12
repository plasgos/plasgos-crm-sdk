<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;

/** `$client->contactGroups` — broadcast audiences. Scope `contacts:read`. */
final class ContactGroups extends AbstractResource
{
    /** @param array{search?: string, page?: int, per_page?: int} $params */
    public function list(array $params = []): Page
    {
        return $this->http->requestPage('/contact-groups', $params);
    }
}
