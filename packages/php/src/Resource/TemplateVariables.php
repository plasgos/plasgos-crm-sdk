<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->templateVariables` — global placeholders for `dynamic` template variables.
 *
 * @phpstan-import-type ListTemplateVariablesData from ApiTypes
 * @phpstan-import-type ListTemplateVariablesMeta from ApiTypes
 */
final class TemplateVariables extends AbstractResource
{
    /**
     * @return Response<ListTemplateVariablesData, ListTemplateVariablesMeta>
     */
    public function list(?RequestOptions $options = null): Response
    {
        /** @var Response<ListTemplateVariablesData, ListTemplateVariablesMeta> $response */
        $response = $this->http->request('GET', '/template-variables', options: $options);
        return $response;
    }
}
