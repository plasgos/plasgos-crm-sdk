<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Tests;

use PHPUnit\Framework\TestCase;
use Plasgos\CrmSdk\Exception\WebhookVerificationException;
use Plasgos\CrmSdk\Webhook\Verifier;

final class WebhookTest extends TestCase
{
    private const PAYLOAD = '{"event":"message.received","user":{"id":42},"timestamp":1775644335}';

    public function testAcceptsMatchingToken(): void
    {
        $event = Verifier::verify(['x-hub-token' => 'secret'], self::PAYLOAD, 'secret');
        self::assertSame(Verifier::EVENT_MESSAGE_RECEIVED, $event['event']);
        self::assertSame(42, $event['user']['id']);
    }

    public function testRejectsMismatchedToken(): void
    {
        $this->expectException(WebhookVerificationException::class);
        Verifier::verify(['X-Hub-Token' => 'wrong'], self::PAYLOAD, 'secret');
    }

    public function testRejectsMissingTokenWhenExpected(): void
    {
        $this->expectException(WebhookVerificationException::class);
        Verifier::verify([], self::PAYLOAD, 'secret');
    }

    public function testSkipsTokenCheckWhenNoneExpected(): void
    {
        $event = Verifier::verify([], self::PAYLOAD);
        self::assertSame('message.received', $event['event']);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(WebhookVerificationException::class);
        Verifier::verify(['x-hub-token' => 't'], 'not json', 't');
    }
}
