<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PublicWebsiteCatalogService
{
    public function __construct(
        private readonly LoanPricingService $pricing,
    ) {}

    /**
     * @return Collection<int, LoanProduct>
     */
    public function publicLoanProducts(): Collection
    {
        return LoanProduct::query()
            ->where('is_active', true)
            ->where('is_public_on_website', true)
            ->whereNotNull('public_website_loan_rate_type_id')
            ->whereHas('publicWebsiteRates')
            ->with([
                'publicWebsiteRateType',
                'publicWebsiteRates' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('tenure_months'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLoanProduct(LoanProduct $loanProduct): array
    {
        $rateType = $loanProduct->publicWebsiteRateType;

        return [
            'id' => $loanProduct->id,
            'code' => $loanProduct->code,
            'name' => $loanProduct->name,
            'description' => $loanProduct->description,
            'category' => $loanProduct->category,
            'loan_rate_type' => $rateType ? [
                'id' => $rateType->id,
                'code' => $rateType->code,
                'name' => $rateType->name,
            ] : null,
            'tenures' => $this->buildTenureOptionsFromRates($loanProduct->publicWebsiteRates),
        ];
    }

    /**
     * Legacy rate-type listing (kept for compatibility).
     *
     * @return Collection<int, LoanRateType>
     */
    public function publicRateTypes(): Collection
    {
        return LoanRateType::query()
            ->where('is_active', true)
            ->where('is_public_on_website', true)
            ->whereHas('loanProduct', fn ($query) => $query->where('is_active', true))
            ->with([
                'loanProduct:id,name,code,category',
                'loanRates' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('tenure_months'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRateType(LoanRateType $rateType): array
    {
        return [
            'id' => $rateType->id,
            'code' => $rateType->code,
            'name' => $rateType->name,
            'description' => $rateType->description,
            'loan_product' => [
                'id' => $rateType->loanProduct->id,
                'name' => $rateType->loanProduct->name,
                'code' => $rateType->loanProduct->code,
                'category' => $rateType->loanProduct->category,
            ],
            'tenures' => $this->buildTenureOptionsFromRates($rateType->loanRates),
        ];
    }

    /**
     * @param  iterable<int, LoanRate>  $rates
     * @return list<array<string, mixed>>
     */
    private function buildTenureOptionsFromRates(iterable $rates): array
    {
        $byTenure = [];

        foreach ($rates as $rate) {
            $months = (int) $rate->tenure_months;
            if ($months < 1) {
                continue;
            }

            if (! isset($byTenure[$months])) {
                $byTenure[$months] = [
                    'tenure_months' => $months,
                    'min_principal' => null,
                    'max_principal' => null,
                ];
            }

            $min = $rate->min_principal !== null ? (float) $rate->min_principal : null;
            $max = $rate->max_principal !== null ? (float) $rate->max_principal : null;

            if ($min !== null) {
                $byTenure[$months]['min_principal'] = $byTenure[$months]['min_principal'] === null
                    ? $min
                    : min($byTenure[$months]['min_principal'], $min);
            }

            if ($max !== null) {
                $byTenure[$months]['max_principal'] = $byTenure[$months]['max_principal'] === null
                    ? $max
                    : max($byTenure[$months]['max_principal'], $max);
            }
        }

        ksort($byTenure);

        return array_values($byTenure);
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteForLoanProduct(int $loanProductId, float $principal, int $tenureMonths): array
    {
        $loanProduct = LoanProduct::query()
            ->whereKey($loanProductId)
            ->where('is_active', true)
            ->where('is_public_on_website', true)
            ->with(['publicWebsiteRateType', 'publicWebsiteRates'])
            ->first();

        if ($loanProduct === null || $loanProduct->publicWebsiteRateType === null) {
            throw new InvalidArgumentException('This loan product is not available on the public website.');
        }

        $rateType = $loanProduct->publicWebsiteRateType;
        $allowedIds = $loanProduct->publicWebsiteRates->pluck('id')->all();

        $loanRate = $this->resolveRateFromAllowedPool($rateType, $tenureMonths, $principal, $allowedIds);

        if ($loanRate === null) {
            throw new InvalidArgumentException('No selected rate applies for this amount and term.');
        }

        return $this->buildQuotePayload($loanProduct, $rateType, $loanRate, $principal, $tenureMonths);
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(int $loanRateTypeId, float $principal, int $tenureMonths): array
    {
        $rateType = LoanRateType::query()
            ->whereKey($loanRateTypeId)
            ->where('is_active', true)
            ->where('is_public_on_website', true)
            ->whereHas('loanProduct', fn ($query) => $query->where('is_active', true))
            ->with('loanProduct')
            ->first();

        if ($rateType === null) {
            throw new InvalidArgumentException('Loan product type is not available on the public website.');
        }

        $loanRate = $this->pricing->resolveRateForAmount($rateType, $tenureMonths, $principal);

        if ($loanRate === null) {
            throw new InvalidArgumentException('No rate applies for this amount and term.');
        }

        return $this->buildQuotePayload($rateType->loanProduct, $rateType, $loanRate, $principal, $tenureMonths);
    }

    /**
     * @param  list<int>  $allowedRateIds
     */
    private function resolveRateFromAllowedPool(
        LoanRateType $rateType,
        int $tenureMonths,
        float $principal,
        array $allowedRateIds,
    ): ?LoanRate {
        if ($allowedRateIds === []) {
            return null;
        }

        $candidates = LoanRate::query()
            ->where('loan_rate_type_id', $rateType->id)
            ->where('is_active', true)
            ->where('tenure_months', $tenureMonths)
            ->whereIn('id', $allowedRateIds)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $principalAmount = (float) $principal;

        $bandMatches = $candidates->filter(
            fn (LoanRate $rate) => $rate->matchesPrincipalAmount($principalAmount)
                && ($rate->min_principal !== null || $rate->max_principal !== null)
        );

        if ($bandMatches->isNotEmpty()) {
            return $this->selectMostSpecificBand($bandMatches);
        }

        $unbanded = $candidates->first(
            fn (LoanRate $rate) => $rate->min_principal === null && $rate->max_principal === null
        );

        return $unbanded ?? $candidates->first();
    }

    /**
     * @param  Collection<int, LoanRate>  $candidates
     */
    private function selectMostSpecificBand(Collection $candidates): LoanRate
    {
        return $candidates->sortByDesc(function (LoanRate $rate) {
            $min = $rate->min_principal !== null ? (float) $rate->min_principal : 0;
            $max = $rate->max_principal !== null ? (float) $rate->max_principal : PHP_FLOAT_MAX;
            $span = $max - $min;

            return [$rate->min_principal !== null ? 1 : 0, -$span];
        })->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuotePayload(
        LoanProduct $loanProduct,
        LoanRateType $rateType,
        LoanRate $loanRate,
        float $principal,
        int $tenureMonths,
    ): array {
        $startDate = Carbon::today();

        $quote = $this->pricing->quoteLoan([
            'principal' => $principal,
            'tenure_months' => $tenureMonths,
            'start_date' => $startDate,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $loanProduct,
        ]);

        return [
            'currency' => 'ZMW',
            'loan_product_id' => $loanProduct->id,
            'loan_rate_type_id' => $rateType->id,
            'loan_rate_id' => $loanRate->id,
            'principal' => $quote['principal'],
            'processing_fee' => $quote['processing_fee'],
            'interest' => $quote['interest'],
            'total_amount' => $quote['total_amount'],
            'installment_amount' => $quote['installment_amount'],
            'installment_count' => count($quote['installments'] ?? []),
            'tenure_months' => $quote['tenure_months'],
            'term_days' => $quote['term_days'],
            'quoted_at' => now()->toIso8601String(),
            'disclaimer' => 'Indicative quote from current rate tables. Final terms are confirmed on the LMS before disbursement.',
        ];
    }
}
