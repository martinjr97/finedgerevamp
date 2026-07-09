<?php

namespace App\Services\Repayments;

use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use Carbon\CarbonInterface;

class RepaymentGatewayTimelineBuilder
{
    /**
     * @return list<array{key: string, label: string, state: 'complete'|'current'|'upcoming'|'skipped', at: ?string}>
     */
    public function build(Repayment $repayment, ?PaymentGatewayAttempt $attempt): array
    {
        if (! $attempt) {
            return [];
        }

        $steps = [
            'created' => ['label' => 'Collection request created', 'complete' => true, 'at' => $attempt->created_at],
            'prompt_sent' => ['label' => 'Payment prompt sent', 'complete' => false, 'at' => $attempt->initiated_at],
            'customer_pending' => ['label' => 'Customer approval pending', 'complete' => false, 'at' => null],
            'gateway_confirmed' => ['label' => 'Gateway confirmed', 'complete' => false, 'at' => $attempt->confirmed_at],
            'loan_updated' => ['label' => 'Loan updated', 'complete' => false, 'at' => null],
            'finance_credited' => ['label' => 'Finance account credited', 'complete' => false, 'at' => $repayment->processed_at],
        ];

        if ($attempt->initiated_at || in_array($attempt->status, [
            GatewayAttemptStatus::Initiated,
            GatewayAttemptStatus::Pending,
            GatewayAttemptStatus::Confirmed,
            GatewayAttemptStatus::Completed,
            GatewayAttemptStatus::Failed,
            GatewayAttemptStatus::Rejected,
            GatewayAttemptStatus::Expired,
        ], true)) {
            $steps['prompt_sent']['complete'] = true;
        }

        if ($attempt->status === GatewayAttemptStatus::Pending
            || ($attempt->status === GatewayAttemptStatus::Initiated && ! $attempt->isTerminal())) {
            $steps['customer_pending']['complete'] = false;
        } elseif ($attempt->status->isSuccessful()) {
            $steps['customer_pending']['complete'] = true;
        } elseif ($attempt->isTerminal() && ! $attempt->status->isSuccessful()) {
            $steps['customer_pending']['complete'] = true;
        }

        if ($attempt->status->isSuccessful()) {
            $steps['gateway_confirmed']['complete'] = true;
        }

        if ($repayment->loanRepayments()->exists()) {
            $steps['loan_updated']['complete'] = true;
            $steps['loan_updated']['at'] = $repayment->loanRepayments()->latest('id')->value('created_at');
        }

        if ($repayment->status === 'completed') {
            $steps['finance_credited']['complete'] = true;
        }

        $currentKey = $this->resolveCurrentStepKey($repayment, $attempt, $steps);

        $timeline = [];
        $seenCurrent = false;
        foreach ($steps as $key => $step) {
            if (! $step['complete'] && $key === 'finance_credited' && $repayment->status === 'failed') {
                continue;
            }

            $state = 'upcoming';
            if ($step['complete']) {
                $state = 'complete';
            } elseif ($key === $currentKey) {
                $state = 'current';
                $seenCurrent = true;
            } elseif (! $seenCurrent && ! $step['complete']) {
                $state = 'current';
                $seenCurrent = true;
            }

            $timeline[] = [
                'key' => $key,
                'label' => $step['label'],
                'state' => $state,
                'at' => $this->formatTimestamp($step['at']),
            ];
        }

        return $timeline;
    }

    /**
     * @param  array<string, array{label: string, complete: bool, at: mixed}>  $steps
     */
    private function resolveCurrentStepKey(Repayment $repayment, PaymentGatewayAttempt $attempt, array $steps): string
    {
        if ($repayment->status === 'completed') {
            return 'finance_credited';
        }

        if ($attempt->status->isSuccessful() && ! ($steps['loan_updated']['complete'] ?? false)) {
            return 'loan_updated';
        }

        if ($attempt->status->isSuccessful()) {
            return 'gateway_confirmed';
        }

        if ($attempt->isTerminal() && ! $attempt->status->isSuccessful()) {
            return 'customer_pending';
        }

        if ($steps['prompt_sent']['complete'] ?? false) {
            return 'customer_pending';
        }

        return 'created';
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return null;
    }
}
