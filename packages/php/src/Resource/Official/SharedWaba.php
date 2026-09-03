<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Resource\Official;

use Plasgos\CrmSdk\Envelope;
use Plasgos\CrmSdk\Resource\AbstractResource;

final class SharedWaba extends AbstractResource
{
    /**
     * Send an OTP through a shared WABA serial token.
     *
     * @param array{to: string, otp_code?: string|null, metadata?: array<string, mixed>|null} $input
     */
    public function sendOtp(string $serialToken, array $input): Envelope
    {
        return $this->http->request(
            'POST',
            '/api/v1/official/shared-waba/otp/' . rawurlencode($serialToken),
            body: self::compact([
                'to' => $input['to'],
                'otp_code' => $input['otp_code'] ?? null,
                'metadata' => $input['metadata'] ?? null,
            ]),
        );
    }
}
