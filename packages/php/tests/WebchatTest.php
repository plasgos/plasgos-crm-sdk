<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Webchat\Webchat;

/** Webchat identity token — golden vectors shared with the Node/Python suites (docs/vectors-v3.json). */
final class WebchatTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function doc(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../../docs/vectors-v3.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function vectors(): iterable
    {
        foreach (self::doc()['webchat_identity'] as $v) {
            yield $v['name'] => [$v];
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidVectors(): iterable
    {
        foreach (self::doc()['webchat_identity_invalid'] as $v) {
            yield $v['name'] => [$v];
        }
    }

    /** @param array<string, mixed> $v */
    private static function sign(array $v): string
    {
        $o = $v['options'];
        return Webchat::signIdentity(
            $v['secret'],
            $v['identity'],
            widgetId: $o['widget_id'] ?? null,
            ttl: $o['ttl'] ?? Webchat::IDENTITY_DEFAULT_TTL,
            now: $o['now'] ?? null,
            jti: $o['jti'] ?? null,
        );
    }

    private static function decode(string $part): string
    {
        return (string) base64_decode(strtr($part, '-_', '+/'), true);
    }

    /** @dataProvider vectors */
    public function testMatchesGoldenVector(array $v): void
    {
        $token = self::sign($v);
        self::assertSame($v['token'], $token);
        [$header, $payload] = explode('.', $token);
        self::assertSame($v['header_json'], self::decode($header));
        self::assertSame($v['payload_json'], self::decode($payload));
    }

    /** @dataProvider invalidVectors */
    public function testRejectsInvalidInput(array $v): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Webchat::signIdentity/');
        self::sign($v);
    }

    public function testDefaultsOneHourCurrentTimeRandomJtiAndValidSignature(): void
    {
        $before = time();
        $token = Webchat::signIdentity('wcs_secret', ['sub' => '7']);
        [$header, $payload, $signature] = explode('.', $token);
        $claims = json_decode(self::decode($payload), true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual($before, $claims['iat']);
        self::assertSame(Webchat::IDENTITY_DEFAULT_TTL, $claims['exp'] - $claims['iat']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $claims['jti']);
        self::assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", 'wcs_secret', true)), '+/', '-_'), '='), $signature);
        self::assertNotSame($token, Webchat::signIdentity('wcs_secret', ['sub' => '7']));
    }

    public function testCountsLengthLikeTheServer(): void
    {
        // 128 astral characters = 256 UTF-16 code units: too long for the server, so rejected here as well.
        $this->expectException(\InvalidArgumentException::class);
        Webchat::signIdentity('wcs_secret', ['sub' => str_repeat("\u{1F600}", 128)]);
    }
}
