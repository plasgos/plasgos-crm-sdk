<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;

/** `$client->templateVariables` — global placeholders for `dynamic` template variables. */
final class TemplateVariables extends AbstractResource
{
    public function list(?RequestOptions $options = null): Response
    {
        return $this->http->request('GET', '/template-variables', options: $options);
    }
}
