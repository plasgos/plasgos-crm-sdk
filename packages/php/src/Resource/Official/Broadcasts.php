<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource\Official;

use Plasgos\CrmSdk\Envelope;
use Plasgos\CrmSdk\Resource\AbstractResource;

final class Broadcasts extends AbstractResource
{
    /**
     * Create a WABA official broadcast (HTTP 201). Max 1000 recipients/day,
     * grouped by the `scheduled_at` date. `request_id` is the idempotency key.
     *
     * @param array{
     *     account_id: string, template_id: string, phone_number_id: string,
     *     name: string, description?: string, scheduled_at: string, request_id: string,
     *     variables?: list<array{name: string, value: string}>,
     *     location?: array<string, mixed>,
     *     recipients: list<array{phone_number: string, attachment?: array<string, mixed>}>
     * } $input
     */
    public function create(array $input): Envelope
    {
        return $this->http->request('POST', '/api/v1/official/broadcasts', body: self::compact([
            'account_id' => $input['account_id'],
            'template_id' => $input['template_id'],
            'phone_number_id' => $input['phone_number_id'],
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'scheduled_at' => $input['scheduled_at'],
            'request_id' => $input['request_id'],
            'variables' => $input['variables'] ?? null,
            'location' => $input['location'] ?? null,
            'recipients' => array_map(
                static fn (array $r): array => self::compact($r),
                $input['recipients'],
            ),
        ]));
    }

    /**
     * @param array{page?: int, limit?: int, status?: string, search?: string} $params
     */
    public function list(array $params = []): Envelope
    {
        return $this->http->request('GET', '/api/v1/official/broadcasts', query: self::compact([
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
            'status' => $params['status'] ?? null,
            'search' => $params['search'] ?? null,
        ]));
    }

    public function monitoring(string $broadcastId): Envelope
    {
        return $this->http->request(
            'GET',
            '/api/v1/official/broadcasts/' . rawurlencode($broadcastId) . '/monitoring',
        );
    }
}
