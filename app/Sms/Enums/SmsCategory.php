<?php

namespace App\Sms\Enums;

enum SmsCategory: string
{
    case Otp = 'otp';
    case Security = 'security';
    case Payment = 'payment';
    case Loan = 'loan';
    case General = 'general';
    case Marketing = 'marketing';

    public function isSensitive(): bool
    {
        return match ($this) {
            self::Otp, self::Security => true,
            default => false,
        };
    }
}
