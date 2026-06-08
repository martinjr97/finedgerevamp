<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use Carbon\Carbon;

trait UsesLoanPricing
{
    protected function loanPricing(): LoanPricingService
    {
        return app(LoanPricingService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLoanPricingQuote(
        float|string $principal,
        int $tenureMonths,
        Carbon $loanStartDate,
        LoanProduct $loanProduct,
        LoanRateType $rateType,
        ?LoanRate $loanRate = null,
        ?int $termDays = null,
        ?Carbon $loanEndDate = null,
    ): array {
        $loanRate ??= $this->loanPricing()->resolveRateForAmount($rateType, $tenureMonths, $principal);

        if ($loanRate === null) {
            throw new \InvalidArgumentException('No active loan rate found for the given tenure and principal.');
        }

        $payload = [
            'principal' => $principal,
            'tenure_months' => $tenureMonths,
            'start_date' => $loanStartDate,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $loanProduct,
        ];

        if ($termDays !== null) {
            $payload['term_days'] = $termDays;
        }

        $quote = $this->loanPricing()->quoteLoan($payload);

        if ($loanEndDate !== null) {
            $quote['loan_end_date'] = $loanEndDate->toDateString();
        }

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loanFinancialAttributesFromQuote(array $quote): array
    {
        return $this->loanPricing()->buildLoanFinancialSnapshot($quote);
    }

    protected function applyPostLoanPricingSetup(Loan $loan): void
    {
        if ($loan->accrual_type === 'at_beginning') {
            $loan->createAtBeginningAccruals();
        }
    }

    /**
     * @param  array<string, mixed>  $financials
     * @return array<string, mixed>
     */
    protected function mergePricingMetadata(array $metadata, array $financials): array
    {
        return array_merge($metadata, $financials['pricing_metadata'] ?? []);
    }
}
