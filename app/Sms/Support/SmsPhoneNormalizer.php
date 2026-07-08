<?php

namespace App\Sms\Support;

use App\PaymentPlatform\Support\ZambiaMsisdnNormalizer;

final class SmsPhoneNormalizer
{
    /**
     * Normalize for Zamtel contacts segment (bracketed international MSISDN).
     */
    public function normalizeForZamtel(string $input): string
    {
        return '['.$this->normalizeInternationalMsisdn($input).']';
    }

    /**
     * International MSISDN without leading plus (e.g. 260977000001).
     */
    public function normalizeInternationalMsisdn(string $input): string
    {
        return ZambiaMsisdnNormalizer::normalizeForCGrate($input, 'international_without_plus');
    }

    /**
     * Provider-aware destination format for queued SMS and send jobs.
     */
    public function normalizeForProvider(string $input, string $provider): string
    {
        return match ($provider) {
            'zamtel', 'zamtel_api' => $this->normalizeForZamtel($input),
            default => $this->normalizeInternationalMsisdn($input),
        };
    }

    /**
     * Normalize for display/logging (international without plus).
     */
    public function normalizeForStorage(string $input): string
    {
        return $this->normalizeInternationalMsisdn($input);
    }

    public function isValid(string $input): bool
    {
        try {
            $this->normalizeForStorage($input);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function mask(string $input): string
    {
        try {
            $normalized = $this->normalizeForStorage($input);
        } catch (\InvalidArgumentException) {
            return '***';
        }

        if (strlen($normalized) < 6) {
            return '***';
        }

        return substr($normalized, 0, 5).'***'.substr($normalized, -2);
    }
}
