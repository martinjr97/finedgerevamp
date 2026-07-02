<?php

namespace App\PaymentPlatform\Support;

use Illuminate\Support\Facades\Log;

/**
 * Safe structured logging for cGrate UAT / verification commands.
 * Never logs passwords or WS-Security secrets.
 */
final class CGrateUatLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = []): void
    {
        if (! (bool) config('cgrate.uat.log_enabled', true)) {
            return;
        }

        Log::channel((string) config('cgrate.uat.log_channel', 'cgrate_uat'))->info($event, [
            'cgrate_uat' => true,
            'event' => $event,
            'recorded_at' => now()->toIso8601String(),
            ...$this->redact($context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);

                continue;
            }

            if (is_string($value)) {
                $redacted[$key] = $this->redactString($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        return str_contains($lower, 'password')
            || str_contains($lower, 'secret')
            || str_contains($lower, 'token')
            || $lower === 'authorization';
    }

    private function redactString(string $value): string
    {
        $redacted = preg_replace(
            '/(<wsse:Password[^>]*>)(.*?)(<\/wsse:Password>)/is',
            '$1[REDACTED]$3',
            $value
        );

        return is_string($redacted) ? $redacted : $value;
    }
}
