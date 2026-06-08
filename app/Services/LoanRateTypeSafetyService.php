<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\Market;
use Illuminate\Support\Collection;

/**
 * Deletion guards for loan rate types and rows.
 *
 * Multi-product sharing: not implemented. See docs/loan-rate-type-architecture.md —
 * copy-to-product is the supported reuse path; a pivot (loan_product_rate_type) would
 * be needed for true shared rate plans across products.
 */
class LoanRateTypeSafetyService
{
    public const RATE_IN_USE_MESSAGE = 'This rate cannot be deleted because it has already been used on loans.';

    public function canDeleteRate(LoanRate $loanRate): bool
    {
        return $this->loansUsingRate($loanRate->id)->isEmpty();
    }

    public function loansUsingRateCount(int $loanRateId): int
    {
        return $this->loansUsingRate($loanRateId)->count();
    }

    /**
     * @return array{allowed: bool, reasons: list<string>}
     */
    public function assessRateTypeDeletion(LoanRateType $loanRateType): array
    {
        $reasons = [];

        $rateIds = $loanRateType->loanRates()->pluck('id');

        if ($rateIds->isNotEmpty()) {
            $loansViaRates = Loan::query()
                ->whereIn('loan_rate_id', $rateIds)
                ->count();

            if ($loansViaRates > 0) {
                $reasons[] = "Used on {$loansViaRates} loan(s) via rate rows.";
            }
        }

        $companies = Company::query()
            ->where('loan_rate_type_id', $loanRateType->id)
            ->count();

        if ($companies > 0) {
            $reasons[] = "Assigned to {$companies} company/companies.";
        }

        $groups = CustomerGroup::query()
            ->where('loan_rate_type_id', $loanRateType->id)
            ->count();

        if ($groups > 0) {
            $reasons[] = "Assigned to {$groups} customer group(s).";
        }

        $markets = Market::query()
            ->where('loan_rate_type_id', $loanRateType->id)
            ->count();

        if ($markets > 0) {
            $reasons[] = "Assigned to {$markets} market(s).";
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function assertRateDeletable(LoanRate $loanRate): void
    {
        if (! $this->canDeleteRate($loanRate)) {
            throw new \InvalidArgumentException(self::RATE_IN_USE_MESSAGE);
        }
    }

    public function assertRateTypeDeletable(LoanRateType $loanRateType): void
    {
        $assessment = $this->assessRateTypeDeletion($loanRateType);

        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException(
                'This rate type cannot be deleted: '.implode(' ', $assessment['reasons'])
            );
        }
    }

    public function deleteRateType(LoanRateType $loanRateType): void
    {
        $this->assertRateTypeDeletable($loanRateType);

        $loanRateType->loanRates()->each(fn (LoanRate $rate) => $rate->delete());
        $loanRateType->delete();
    }

    /**
     * @return Collection<int, Loan>
     */
    private function loansUsingRate(int $loanRateId): Collection
    {
        return Loan::query()
            ->where('loan_rate_id', $loanRateId)
            ->get(['id', 'loan_number']);
    }
}
