<?php

namespace App\Sms\DTOs;

final class SmsResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly string $provider,
        public readonly bool $successful,
        public readonly bool $accepted,
        public readonly bool $retryable,
        public readonly ?string $providerReference = null,
        public readonly ?int $httpStatus = null,
        public readonly ?string $responseCode = null,
        public readonly ?string $responseMessage = null,
        public readonly array $rawResponse = [],
        public readonly ?string $error = null,
        public readonly bool $skipped = false,
    ) {
    }

    public function success(): bool
    {
        return $this->successful && $this->accepted && ! $this->skipped;
    }

    public function failed(): bool
    {
        return ! $this->skipped && ! $this->success();
    }

    public function skipped(): bool
    {
        return $this->skipped;
    }

    public function shouldRetry(): bool
    {
        return $this->retryable && ! $this->skipped;
    }

    public static function skippedResult(string $provider, string $reason): self
    {
        return new self(
            provider: $provider,
            successful: false,
            accepted: false,
            retryable: false,
            responseMessage: $reason,
            rawResponse: ['success' => false, 'message' => $reason],
            error: $reason,
            skipped: true,
        );
    }

    public static function fromHealth(string $provider, bool $ok, string $message, array $details = []): self
    {
        return new self(
            provider: $provider,
            successful: $ok,
            accepted: $ok,
            retryable: false,
            responseMessage: $message,
            rawResponse: array_merge(['success' => $ok, 'message' => $message], $details),
            error: $ok ? null : $message,
        );
    }
}
