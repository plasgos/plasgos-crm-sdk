<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\Page;
use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->contactGroups` — broadcast audiences. Scope `contacts:read`.
 *
 * @phpstan-import-type ListContactGroupsQuery from ApiTypes
 * @phpstan-import-type ListContactGroupsItem from ApiTypes
 */
final class ContactGroups extends AbstractResource
{
    /**
     * @param ListContactGroupsQuery $params
     * @return Page<ListContactGroupsItem>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        /** @var Page<ListContactGroupsItem> $page */
        $page = $this->http->requestPage('/contact-groups', $params, $options);
        return $page;
    }
}
