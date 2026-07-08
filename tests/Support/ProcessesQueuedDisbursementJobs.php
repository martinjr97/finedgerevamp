<?php

namespace Tests\Support;

use App\Models\Loan;
use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Jobs\DispatchGatewayDisbursementJob;
use App\PaymentPlatform\Services\GatewayIntegrationService;

trait ProcessesQueuedDisbursementJobs
{
    protected function runQueuedDisbursementJob(Loan $loan): PaymentGatewayAttempt
    {
        $attempt = PaymentGatewayAttempt::query()
            ->where('attemptable_type', Loan::class)
            ->where('attemptable_id', $loan->id)
            ->where('direction', GatewayDirection::Disbursement)
            ->latest('id')
            ->firstOrFail();

        (new DispatchGatewayDisbursementJob($attempt->id))
            ->handle(app(GatewayIntegrationService::class));

        return $attempt->fresh();
    }
}
