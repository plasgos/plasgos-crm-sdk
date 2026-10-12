<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Exception\WebhookVerificationException;
use Plasgos\CrmSdk\Webhook\EventType;
use Plasgos\CrmSdk\Webhook\Webhook;

final class WebhookTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function vector(): array
    {
        $doc = json_decode((string) file_get_contents(__DIR__ . '/../../../docs/vectors-v3.json'), true, 512, JSON_THROW_ON_ERROR);
        return $doc['webhook'][0];
    }

    public function testGoldenVectorVerifiesWithAnyHeaderStyle(): void
    {
        $v = self::vector();
        $headers = ['Plasgos-Webhook-Id' => $v['id'], 'Plasgos-Webhook-Timestamp' => (string) $v['timestamp'], 'Plasgos-Webhook-Signature' => $v['signature']];
        self::assertSame($headers, Webhook::signatureHeaders($v['id'], $v['timestamp'], $v['body'], $v['secret']));

        $event = Webhook::verify($v['body'], array_change_key_case($headers), $v['secret'], now: $v['timestamp'] + 10);
        self::assertSame(EventType::MESSAGE_RECEIVED, $event['type']);

        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        self::assertSame('msg_1', Webhook::verify($v['body'], $server, $v['secret'], now: $v['timestamp'])['data']['id']);

        $request = new ServerRequest('POST', '/hook', $headers, $v['body']);
        self::assertSame($v['id'], Webhook::verifyRequest($request, $v['secret'], PHP_INT_MAX)['id']);
    }

    public function testPreviousSecretAcceptedDuringRotation(): void
    {
        $v = self::vector();
        $headers = Webhook::signatureHeaders($v['id'], $v['timestamp'], $v['body'], 'whsec_old');
        self::assertSame($v['id'], Webhook::verify($v['body'], $headers, ['whsec_new', 'whsec_old'], now: $v['timestamp'])['id']);
    }

    /** @return iterable<string, array{\Closure, string}> */
    public static function tampered(): iterable
    {
        yield 'body changed' => [static fn (array $v, array $h) => [str_replace('msg_1', 'msg_2', $v['body']), $h, $v['timestamp']], 'mismatch'];
        yield 'too old' => [static fn (array $v, array $h) => [$v['body'], $h, $v['timestamp'] + 301], 'tolerance'];
        yield 'header missing' => [static function (array $v, array $h) { unset($h['Plasgos-Webhook-Signature']); return [$v['body'], $h, $v['timestamp']]; }, 'missing'];
        yield 'unknown version' => [static function (array $v, array $h) { $h['Plasgos-Webhook-Signature'] = 'v0=abc'; return [$v['body'], $h, $v['timestamp']]; }, 'unsupported'];
        yield 'other secret' => [static fn (array $v, array $h) => [$v['body'], Webhook::signatureHeaders($v['id'], $v['timestamp'], $v['body'], 'whsec_x'), $v['timestamp']], 'mismatch'];
        yield 'id differs from body' => [static function (array $v) {
            $body = str_replace($v['id'], 'evt_other', $v['body']);
            return [$body, Webhook::signatureHeaders($v['id'], $v['timestamp'], $body, $v['secret']), $v['timestamp']];
        }, 'does not match'];
    }

    /** @dataProvider tampered */
    public function testRejectsTampering(\Closure $mutate, string $message): void
    {
        $v = self::vector();
        [$body, $headers, $now] = $mutate($v, Webhook::signatureHeaders($v['id'], $v['timestamp'], $v['body'], $v['secret']));
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage($message);
        Webhook::verify($body, $headers, $v['secret'], now: $now);
    }
}
