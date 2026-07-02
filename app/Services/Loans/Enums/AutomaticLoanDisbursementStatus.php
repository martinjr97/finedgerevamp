<?php

namespace App\Services\Loans\Enums;

enum AutomaticLoanDisbursementStatus: string
{
    case SkippedNoAutoRoute = 'skipped_no_auto_route';
    case SkippedRouteDisabled = 'skipped_route_disabled';
    case SkippedAutoProcessOff = 'skipped_auto_process_off';
    case SkippedInvalidDestination = 'skipped_invalid_destination';
    case SkippedGatewayNotReady = 'skipped_gateway_not_ready';
    case SkippedExistingAttempt = 'skipped_existing_attempt';
    case Initiated = 'initiated';
    case Failed = 'failed';

    public function isManualFallback(): bool
    {
        return in_array($this, [
            self::SkippedNoAutoRoute,
            self::SkippedRouteDisabled,
            self::SkippedAutoProcessOff,
            self::SkippedInvalidDestination,
            self::SkippedGatewayNotReady,
            self::SkippedExistingAttempt,
            self::Failed,
        ], true);
    }

    public function usesWarningFlash(): bool
    {
        return in_array($this, [
            self::SkippedInvalidDestination,
            self::SkippedGatewayNotReady,
            self::SkippedExistingAttempt,
            self::Failed,
        ], true);
    }
}
