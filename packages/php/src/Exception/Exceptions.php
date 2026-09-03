<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Exception;

/** Base class for every error raised by the SDK. */
class PlasgosApiException extends \RuntimeException
{
    /**
     * @param list<array{message: string, path?: array<int, string|int>}> $errors
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly int|string|null $apiStatus = null,
        public readonly array $errors = [],
        public readonly mixed $raw = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

/** 401 — missing or invalid authentication headers. */
class AuthenticationException extends PlasgosApiException {}

/** 403 — valid credentials but scope/context not allowed. */
class PermissionException extends PlasgosApiException {}

/** 403 — the subscription plan does not include API access. */
class PlanException extends PermissionException {}

/** 404 — resource not found. */
class NotFoundException extends PlasgosApiException {}

/** 408 — x-timestamp outside the allowed window (clock skew). */
class SignatureExpiredException extends PlasgosApiException {}

/** 409 — duplicate message_id. */
class ConflictException extends PlasgosApiException {}

/** 400/422 — request body failed validation; see ->errors. */
class ValidationException extends PlasgosApiException {}

/** 400 — generic bad request. */
class BadRequestException extends PlasgosApiException {}

/** 429 — daily API hit quota exceeded. */
class RateLimitException extends PlasgosApiException {}

/** >= 500 — server-side failure. */
class ServerException extends PlasgosApiException {}

/** Network failure / timeout / DNS — no response was received. */
class TransportException extends PlasgosApiException {}

/** Raised by the webhook Verifier on token mismatch or invalid JSON. */
class WebhookVerificationException extends \RuntimeException {}

final class ExceptionFactory
{
    /**
     * @param list<array{message: string, path?: array<int, string|int>}> $errors
     */
    public static function fromResponse(
        int $httpStatus,
        int|string|null $apiStatus,
        string $message,
        array $errors,
        mixed $raw,
        ?string $requestId,
    ): PlasgosApiException {
        $args = [$message ?: "Request failed with status {$httpStatus}", $httpStatus, $apiStatus, $errors, $raw, $requestId];

        $looksLikePlan = $httpStatus === 403
            && preg_match('/tidak menyertakan|does not include api|plan/i', $message) === 1;

        return match (true) {
            $httpStatus === 401 => new AuthenticationException(...$args),
            $httpStatus === 403 && $looksLikePlan => new PlanException(...$args),
            $httpStatus === 403 => new PermissionException(...$args),
            $httpStatus === 404 => new NotFoundException(...$args),
            $httpStatus === 408 => new SignatureExpiredException(...$args),
            $httpStatus === 409 => new ConflictException(...$args),
            $httpStatus === 422 => new ValidationException(...$args),
            $httpStatus === 429 => new RateLimitException(...$args),
            $httpStatus === 400 && $errors !== [] => new ValidationException(...$args),
            $httpStatus === 400 => new BadRequestException(...$args),
            $httpStatus >= 500 => new ServerException(...$args),
            default => new PlasgosApiException(...$args),
        };
    }
}
