<?php

namespace App\Services\Repayments;

use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\Services\Repayments\DTOs\RepaymentGatewayShowState;

class RepaymentGatewayShowStateService
{
    public function forRepayment(Repayment $repayment): RepaymentGatewayShowState
    {
        $attempt = $this->resolveCollectionAttempt($repayment);

        if ($attempt) {
            $attempt->loadMissing('paymentGateway');
        }

        return RepaymentGatewayShowState::fromRepayment($repayment, $attempt);
    }

    public function resolveCollectionAttempt(Repayment $repayment): ?PaymentGatewayAttempt
    {
        if ($repayment->relationLoaded('gatewayAttempt') && $repayment->gatewayAttempt) {
            return $repayment->gatewayAttempt;
        }

        if ($repayment->payment_gateway_attempt_id) {
            return PaymentGatewayAttempt::query()
                ->with('paymentGateway')
                ->find($repayment->payment_gateway_attempt_id);
        }

        return PaymentGatewayAttempt::query()
            ->with('paymentGateway')
            ->where('attemptable_type', Repayment::class)
            ->where('attemptable_id', $repayment->id)
            ->where('direction', GatewayDirection::Collection)
            ->latest('id')
            ->first();
    }
}
