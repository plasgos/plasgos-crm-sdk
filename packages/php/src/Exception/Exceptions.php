<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Exception;

use Plasgos\CrmSdk\Http\Json;

/**
 * Base class for every error raised by the SDK.
 *
 * API v3 errors also carry the machine-readable contract of the error envelope
 * (`{ success:false, message, error:{ code, type, details, fields, doc_url }, meta:{ request_id } }`):
 * branch on {@see $errorCode}; show {@see getMessage()} (Indonesian, user-facing) to people.
 */
class PlasgosApiException extends \RuntimeException
{
    /**
     * @param list<array{message: string, path?: array<int, string|int>}> $errors
     * @param array<string, mixed>|null $details
     * @param list<array{field: string, code: string, message: string}> $fields
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly int|string|null $apiStatus = null,
        public readonly array $errors = [],
        public readonly mixed $raw = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
        /** v3: stable machine code, e.g. "requires_template". Null for legacy responses. */
        public readonly ?string $errorCode = null,
        /** v3: error family, e.g. "invalid_request", "authentication_error". */
        public readonly ?string $errorType = null,
        /** v3: context for screens, e.g. the conflicting contact id. */
        public readonly ?array $details = null,
        /** v3: per-field validation errors (dot-notation `field`). */
        public readonly array $fields = [],
        public readonly ?string $docUrl = null,
        /** Seconds suggested by `Retry-After`, when the server sent one. */
        public readonly ?int $retryAfter = null,
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

/** Timestamp outside the allowed window (clock skew): v3 401 `signature_expired`, legacy 408. */
class SignatureExpiredException extends AuthenticationException {}

/** 409 — duplicate message_id. */
class ConflictException extends PlasgosApiException {}

/** 400/422 — request body failed validation; see ->errors. */
class ValidationException extends PlasgosApiException {}

/** 400 — generic bad request. */
class BadRequestException extends PlasgosApiException {}

/** 429 — per-minute limit (`rate_limited`) or daily quota (`daily_quota_exceeded`); see ->retryAfter. */
class RateLimitException extends PlasgosApiException {}

/** >= 500 — server-side failure. */
class ServerException extends PlasgosApiException {}

/** v3 502 — the channel provider (Meta, WhatsApp) rejected or failed the request. */
class ChannelException extends ServerException {}

/** v3 503 — service or channel temporarily unavailable; safe to retry later. */
class ServiceUnavailableException extends ServerException {}

/** Network failure / timeout / DNS — no response was received. */
class TransportException extends PlasgosApiException {}

/** Raised by the webhook Verifier on token mismatch or invalid JSON. */
class WebhookVerificationException extends \RuntimeException {}

final class ExceptionFactory
{
    /**
     * Build a typed exception from an API v3 error response (docs/SDK_SPEC.md §5).
     *
     * @param array<string, mixed>|null $body decoded JSON body, null when not JSON
     */
    public static function fromV3(int $httpStatus, ?array $body, ?string $requestId, ?int $retryAfter): PlasgosApiException
    {
        $error = Json::object($body['error'] ?? null) ?? [];
        $message = Json::string($body['message'] ?? null);
        if ($message === '') {
            $message = "Plasgos CRM responded with HTTP {$httpStatus}";
        }
        $code = Json::stringOrNull($error['code'] ?? null) ?? ($body === null ? 'unexpected_response' : 'http_' . $httpStatus);
        $type = Json::stringOrNull($error['type'] ?? null);
        $fields = [];
        foreach (is_array($error['fields'] ?? null) ? $error['fields'] : [] as $field) {
            if (is_array($field)) {
                $fields[] = [
                    'field' => Json::string($field['field'] ?? null),
                    'code' => Json::string($field['code'] ?? null),
                    'message' => Json::string($field['message'] ?? null),
                ];
            }
        }
        $issues = array_map(
            static fn (array $f): array => ['message' => $f['message'], 'path' => $f['field'] === '' ? [] : explode('.', $f['field'])],
            $fields,
        );
        $metaRequestId = Json::stringOrNull(Json::object($body['meta'] ?? null)['request_id'] ?? null);

        $args = [
            'message' => $message,
            'httpStatus' => $httpStatus,
            'apiStatus' => $code,
            'errors' => $issues,
            'raw' => $body,
            'requestId' => $metaRequestId ?? $requestId,
            'errorCode' => $code,
            'errorType' => $type,
            'details' => Json::object($error['details'] ?? null),
            'fields' => $fields,
            'docUrl' => Json::stringOrNull($error['doc_url'] ?? null),
            'retryAfter' => $retryAfter,
        ];

        return match (true) {
            $httpStatus === 401 && $code === 'signature_expired' => new SignatureExpiredException(...$args),
            $httpStatus === 401 => new AuthenticationException(...$args),
            $httpStatus === 403 && $code === 'not_entitled' => new PlanException(...$args),
            $httpStatus === 403 => new PermissionException(...$args),
            $httpStatus === 404 => new NotFoundException(...$args),
            $httpStatus === 409 => new ConflictException(...$args),
            $httpStatus === 400 && $fields === [] => new BadRequestException(...$args),
            $httpStatus === 400, $httpStatus === 413, $httpStatus === 422 => new ValidationException(...$args),
            $httpStatus === 429 => new RateLimitException(...$args),
            $httpStatus === 502 => new ChannelException(...$args),
            $httpStatus === 503 => new ServiceUnavailableException(...$args),
            $httpStatus >= 500 => new ServerException(...$args),
            default => new PlasgosApiException(...$args),
        };
    }

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
