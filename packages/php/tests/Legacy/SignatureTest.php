<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests\Legacy;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Legacy\Signature;

final class SignatureTest extends TestCase
{
    /**
     * @return list<array{string, string, int, string}>
     */
    public static function vectors(): array
    {
        $path = __DIR__ . '/../../../../docs/vectors.json';
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        return array_map(
            static fn (array $v): array => [$v['api_key'], $v['secret_key'], $v['timestamp'], $v['signature']],
            $data['vectors'],
        );
    }

    /**
     * @dataProvider vectors
     */
    public function testMatchesGoldenVectors(string $apiKey, string $secret, int $ts, string $expected): void
    {
        self::assertSame($expected, Signature::compute($apiKey, $secret, $ts));
    }

    public function testHeaders(): void
    {
        self::assertSame(
            [
                'x-api-key' => 'test_key',
                'x-timestamp' => '1700000000',
                'x-signature' => 'e18a0c152134399f8efa60f058ced975a2f24657e231c3bd95351d527a3c1fcb',
            ],
            Signature::headers('test_key', 'test_secret', 1700000000),
        );
    }
}
