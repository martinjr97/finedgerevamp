<?php

namespace App\Sms\Support;

final class SmsMessageValidator
{
    public function maxLength(): int
    {
        return max(1, (int) config('sms.max_length', 159));
    }

    public function isValidLength(string $message): bool
    {
        return mb_strlen($message) <= $this->maxLength();
    }

    public function assertValidLength(string $message): void
    {
        if (! $this->isValidLength($message)) {
            throw new \InvalidArgumentException(sprintf(
                'SMS message exceeds maximum length of %d characters (got %d).',
                $this->maxLength(),
                mb_strlen($message),
            ));
        }
    }
}
