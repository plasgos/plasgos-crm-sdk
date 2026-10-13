<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests\Types;

use Plasgos\CrmSdk\Client;
use Plasgos\CrmSdk\Content;
use Plasgos\CrmSdk\RequestOptions;

use function PHPStan\Testing\assertType;

/**
 * Checked by PHPStan (phpstan.neon.dist), never executed: the generated types reach integrators —
 * `data`, `meta`, page items and request bodies are typed per operation from OpenAPI.
 *
 * @phpstan-import-type Contact from \Plasgos\CrmSdk\Types\ApiTypes
 */
final class ApiTypesUsage
{
    public function __construct(private readonly Client $client) {}

    public function responsesAreTypedPerOperation(): void
    {
        $contact = $this->client->contacts->get('ct_1');
        assertType('string', $contact->data['id']);
        assertType('list<string>', $contact->data['tags']);
        assertType('array<string, mixed>', $contact->data['custom_fields']);

        $upsert = $this->client->contacts->upsert(['phone_number' => '0812', 'tags' => ['vip']], 'wc-1', new RequestOptions(timeout: 5));
        assertType('bool', $upsert->meta['created']);

        $me = $this->client->me->get();
        assertType("'active'", $me->data['api_key']['status']);
        assertType('string', $me->meta['request_id']);

        foreach ($this->client->contacts->list(['tag' => 'vip'])->autoPaging() as $item) {
            assertType('string', $item['id']);
        }
    }

    public function bodiesAcceptTheBuilders(): void
    {
        $sent = $this->client->messages->send([
            'endpoint_id' => '6281100000001',
            'contact' => ['phone_number' => '081234567890', 'name' => 'Budi'],
            'content' => Content::template('66f1')->dynamic('name', '{{full_name}}'),
        ]);
        assertType("'delivered'|'failed'|'pending'|'read'|'sent'", $sent->data['status']);

        $this->client->messages->send([
            'endpoint_id' => '6281100000001',
            'contact' => ['id' => 'ct_1'],
            'content' => Content::text('Halo'),
        ]);
    }

    /** @param Contact $contact */
    public function schemasCanBeImported(array $contact): void
    {
        assertType('string|null', $contact['email']);
    }
}
