<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly ?int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $param = null,
        public readonly ?int $retryAfter = null,
        public readonly bool $retryable = false,
        public readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $error = is_array($body) && is_array($body['error'] ?? null) ? $body['error'] : [];

        $code = is_string($error['code'] ?? null) ? $error['code'] : 'http_'.$response->status();
        $message = is_string($error['message'] ?? null) ? $error['message'] : 'Pixelforge returned an unexpected response.';
        $param = is_string($error['param'] ?? null) ? $error['param'] : null;
        $errors = is_array($error['errors'] ?? null) ? $error['errors'] : [];
        $retryAfter = self::retryAfter($response);
        $retryable = in_array($response->status(), [429, 500, 502, 503, 504], true)
            && $code !== 'daily_limit_exceeded';

        return new self(
            status: $response->status(),
            errorCode: $code,
            message: $message,
            param: $param,
            retryAfter: $retryAfter,
            retryable: $retryable,
            errors: $errors,
        );
    }

    public static function network(ConnectionException $exception): self
    {
        $timeout = str_contains(strtolower($exception->getMessage()), 'timed out')
            || str_contains($exception->getMessage(), '28');

        return new self(
            status: null,
            errorCode: $timeout ? 'timeout' : 'network_error',
            message: $timeout ? 'The Pixelforge request timed out.' : 'The Pixelforge request could not be completed.',
            retryable: true,
            previous: $exception,
        );
    }

    public static function invalidResponse(string $message = 'Pixelforge returned an unexpected response.'): self
    {
        return new self(
            status: null,
            errorCode: 'invalid_response',
            message: $message,
            retryable: true,
        );
    }

    public static function configuration(string $message = 'Pixelforge is not configured.'): self
    {
        return new self(
            status: null,
            errorCode: 'invalid_configuration',
            message: $message,
        );
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isRejection(): bool
    {
        return in_array($this->errorCode, [
            'content_policy_violation',
            'validation_failed',
            'invalid_api_key',
            'not_found',
            'idempotency_key_reused',
            'daily_limit_exceeded',
            'invalid_configuration',
        ], true);
    }

    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'content_policy_violation' => 'Pixelforge could not accept this prompt. Try removing sensitive, explicit, or unsafe content, then generate again. Nothing was charged.',
            'daily_limit_exceeded' => 'Pixelforge has reached its daily generation limit for this account. Nothing was charged. The quota resets at 00:00 UTC.',
            'invalid_api_key', 'invalid_configuration' => 'The studio could not reach Pixelforge with a valid key. Nothing was charged. The studio operator has been alerted.',
            'validation_failed' => 'Pixelforge rejected one of the selected options. Nothing was charged. Adjust the setup and try again.',
            'idempotency_key_reused' => 'This request collided with an earlier attempt. Nothing was charged. Start a fresh generation.',
            'not_found' => 'Pixelforge could not find this generation. Nothing was charged.',
            'rate_limit_exceeded' => 'Pixelforge is receiving too many requests right now. Nothing was charged. Try again in a moment.',
            'concurrency_limit_exceeded' => 'Pixelforge is already working on the maximum number of images. Nothing was charged. Try again shortly.',
            'overloaded' => 'Pixelforge is briefly overloaded. Nothing was charged. It is safe to try again.',
            'timeout', 'network_error' => 'We could not confirm the request with Pixelforge. We will keep checking and will never charge you twice. Nothing has been charged yet.',
            default => 'The generation could not be completed. Nothing was charged. Please try again.',
        };
    }

    private static function retryAfter(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }
}
