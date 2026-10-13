<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy\Resource\Official;

use Plasgos\CrmSdk\Legacy\Envelope;
use Plasgos\CrmSdk\Legacy\Resource\AbstractResource;

final class Messages extends AbstractResource
{
    /**
     * Send a WABA official template message.
     *
     * @param array{
     *     phone_number_id: string,
     *     to: string,
     *     template: array{
     *         id: string, name: string, category: string,
     *         parameters?: array{
     *             variables?: list<array{type: string, name: string, value: string}>,
     *             attachment?: array{type: string, link: string, mime_type: string, filename: string, original_name?: string, filesize?: int|float}
     *         }
     *     }
     * } $input
     * @return Envelope<mixed>
     */
    public function sendTemplate(array $input): Envelope
    {
        $body = self::compact([
            'phone_number_id' => $input['phone_number_id'],
            'to' => $input['to'],
            'template' => self::compact([
                'id' => $input['template']['id'],
                'name' => $input['template']['name'],
                'category' => $input['template']['category'],
                'parameters' => isset($input['template']['parameters'])
                    ? self::compact($input['template']['parameters'])
                    : null,
            ]),
        ]);

        return $this->http->request('POST', '/api/v1/official/messages', body: $body);
    }
}
