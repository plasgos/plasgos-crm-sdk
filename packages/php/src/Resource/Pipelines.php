<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource;

use Plasgos\CrmSdk\RequestOptions;
use Plasgos\CrmSdk\Response;
use Plasgos\CrmSdk\Types\ApiTypes;

/**
 * `$client->pipelines` — pipelines with their stages (read only). Scope `leads:read`.
 *
 * @phpstan-import-type ListPipelinesData from ApiTypes
 * @phpstan-import-type ListPipelinesMeta from ApiTypes
 */
final class Pipelines extends AbstractResource
{
    /**
     * @return Response<ListPipelinesData, ListPipelinesMeta>
     */
    public function list(?RequestOptions $options = null): Response
    {
        /** @var Response<ListPipelinesData, ListPipelinesMeta> $response */
        $response = $this->http->request('GET', '/pipelines', options: $options);
        return $response;
    }
}
