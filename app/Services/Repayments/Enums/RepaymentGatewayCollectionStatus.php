<?php

namespace App\Services\Repayments\Enums;

enum RepaymentGatewayCollectionStatus: string
{
    case Initiated = 'initiated';
    case ManualPending = 'manual_pending';
    case FallbackManual = 'fallback_manual';
    case Failed = 'failed';

    public function usesWarningFlash(): bool
    {
        return in_array($this, [self::FallbackManual], true);
    }
}
