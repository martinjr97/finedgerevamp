<?php

namespace App\PaymentPlatform\Support;

use App\Models\Loan;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayDirection;

class GatewayHorizonTagBuilder
{
    /**
     * @return list<string>
     */
    public function build(?PaymentGatewayAttempt $attempt, string $jobKind): array
    {
        if (! $attempt) {
            return [$jobKind];
        }

        $attempt->loadMissing(['paymentGateway', 'attemptable']);

        $tags = [$jobKind];

        $tags[] = 'direction:'.$attempt->direction->value;
        $tags[] = 'correlation:'.$attempt->correlationId();

        $gateway = $attempt->paymentGateway;
        if ($gateway) {
            $tags[] = 'gateway:'.$gateway->code;
        }

        foreach ($this->resolveEntityTags($attempt) as $tag) {
            $tags[] = $tag;
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return list<string>
     */
    private function resolveEntityTags(PaymentGatewayAttempt $attempt): array
    {
        $tags = [];

        if ($attempt->direction === GatewayDirection::Disbursement) {
            $loan = $attempt->attemptable;
            if ($loan instanceof Loan) {
                $tags[] = 'loan:'.$loan->id;
                if ($loan->customer_id) {
                    $tags[] = 'customer:'.$loan->customer_id;
                }
            }

            $gateway = $attempt->paymentGateway;
            if ($gateway
                && $gateway->financial_account_type === FinancialAccountType::Wallet
                && $gateway->financial_account_id) {
                $tags[] = 'wallet:'.$gateway->financial_account_id;
            }

            return $tags;
        }

        $repayment = $attempt->attemptable;
        if ($repayment instanceof Repayment) {
            if ($repayment->customer_id) {
                $tags[] = 'customer:'.$repayment->customer_id;
            }

            $loanId = $repayment->loanRepayments()->value('loan_id');
            if ($loanId) {
                $tags[] = 'loan:'.$loanId;
            }
        }

        return $tags;
    }
}
