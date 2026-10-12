<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Signature;

/** API v3 golden vectors shared with the Node/Python suites (docs/vectors-v3.json). */
final class SignatureTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function vectors(): iterable
    {
        $doc = json_decode((string) file_get_contents(__DIR__ . '/../../../docs/vectors-v3.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($doc['request'] as $i => $v) {
            yield "#{$i} {$v['method']} {$v['request_target']}" => [$v];
        }
    }

    /** @dataProvider vectors */
    public function testMatchesGoldenVector(array $v): void
    {
        self::assertSame($v['string_to_sign'], Signature::stringToSign($v['timestamp'], $v['method'], $v['request_target'], $v['body']));
        self::assertSame($v['signature'], Signature::sign($v['secret_key'], $v['timestamp'], $v['method'], $v['request_target'], $v['body']));
        self::assertSame(
            ['Plasgos-Api-Key' => $v['api_key'], 'Plasgos-Timestamp' => (string) $v['timestamp'], 'Plasgos-Signature' => $v['signature']],
            Signature::headers($v['api_key'], $v['secret_key'], $v['method'], $v['request_target'], $v['body'], $v['timestamp']),
        );
    }

    public function testEverySignedPartChangesTheSignature(): void
    {
        $base = Signature::sign('s', 100, 'POST', '/api/v3/messages', '{"a":1}');
        self::assertNotSame($base, Signature::sign('s', 101, 'POST', '/api/v3/messages', '{"a":1}'));
        self::assertNotSame($base, Signature::sign('s', 100, 'PATCH', '/api/v3/messages', '{"a":1}'));
        self::assertNotSame($base, Signature::sign('s', 100, 'POST', '/api/v3/contacts', '{"a":1}'));
        self::assertNotSame($base, Signature::sign('s', 100, 'POST', '/api/v3/messages', '{"a":2}'));
        self::assertNotSame($base, Signature::sign('t', 100, 'POST', '/api/v3/messages', '{"a":1}'));
    }
}
