<?php

namespace App\PaymentPlatform\Enums;

enum GatewayAttemptStatus: string
{
    case Created = 'created';
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::Completed,
            self::Failed,
            self::Rejected,
            self::Expired,
            self::Cancelled,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Confirmed, self::Completed], true);
    }
}
