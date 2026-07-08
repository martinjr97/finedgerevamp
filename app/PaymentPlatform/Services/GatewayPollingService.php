<?php

namespace App\PaymentPlatform\Services;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Jobs\QueryGatewayAttemptStatusJob;

class GatewayPollingService
{
    public function dispatchDueAttempts(): int
    {
        if ((string) config('queue.default') === 'sync') {
            return 0;
        }

        $dispatched = 0;

        PaymentGatewayAttempt::query()
            ->where('status', GatewayAttemptStatus::Pending)
            ->where('direction', GatewayDirection::Collection)
            ->whereNotNull('next_query_at')
            ->where('next_query_at', '<=', now())
            ->orderBy('next_query_at')
            ->limit(100)
            ->pluck('id')
            ->each(function (int $attemptId) use (&$dispatched) {
                QueryGatewayAttemptStatusJob::dispatchForAttempt($attemptId);

                $dispatched++;
            });

        return $dispatched;
    }
}
