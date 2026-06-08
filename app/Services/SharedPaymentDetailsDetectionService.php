<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPaymentDetail;
use App\Models\DuplicateAlert;
use App\Models\Loan;
use App\Support\PhoneNumberFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SharedPaymentDetailsDetectionService
{
    /**
     * Find other customers who share this loan's disbursement / payment credentials.
     *
     * @return array{
     *     has_matches: bool,
     *     total_count: int,
     *     matches: list<array{
     *         customer_id: int,
     *         customer_name: string,
     *         customer_status: string|null,
     *         loan_id: int|null,
     *         loan_number: string|null,
     *         source: string,
     *         match_reason: string,
     *         matched_credential: string
     *     }>
     * }
     */
    public function forLoan(Loan $loan): array
    {
        $loan->loadMissing(['customer', 'channel']);

        if ($loan->hasCashDestination()) {
            return $this->emptyResult();
        }

        $clearedKeys = $this->clearedAlertKeys((int) $loan->customer_id);
        $matches = collect();

        if ($loan->hasMobileWalletDestination() && filled($loan->disbursement_phone_number)) {
            $matches = $matches->merge(
                $this->matchesForWalletPhone($loan, (string) $loan->disbursement_phone_number, $clearedKeys)
            );
        }

        if ($loan->hasBankDestination() && filled($loan->disbursement_account_number)) {
            $matches = $matches->merge(
                $this->matchesForBankAccount($loan, $clearedKeys)
            );
        }

        $unique = $matches
            ->unique(fn (array $row): string => $row['customer_id'].'|'.($row['loan_id'] ?? 'profile').'|'.$row['source'])
            ->values();

        return [
            'has_matches' => $unique->isNotEmpty(),
            'total_count' => $unique->count(),
            'matches' => $unique->all(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function clearedAlertKeys(int $customerId): Collection
    {
        return DuplicateAlert::query()
            ->where('customer_id', $customerId)
            ->whereIn('match_type', ['same_disbursement_phone', 'same_disbursement_bank_account'])
            ->whereNotNull('cleared_at')
            ->get()
            ->map(fn (DuplicateAlert $alert): string => $this->clearedKey(
                (int) $alert->duplicate_customer_id,
                (string) $alert->match_type,
                $alert->match_value
            ));
    }

    private function clearedKey(int $duplicateCustomerId, string $matchType, ?string $matchValue): string
    {
        return $duplicateCustomerId.'_'.$matchType.'_'.($matchValue ?? '');
    }

    private function isCleared(Collection $clearedKeys, int $duplicateCustomerId, string $matchType, ?string $matchValue): bool
    {
        return $clearedKeys->contains($this->clearedKey($duplicateCustomerId, $matchType, $matchValue));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function matchesForWalletPhone(Loan $loan, string $phone, Collection $clearedKeys): Collection
    {
        $candidates = $this->phoneCandidates($phone);
        if ($candidates === []) {
            return collect();
        }

        $matches = collect();
        $matchType = 'same_disbursement_phone';
        $credentialLabel = $candidates[0];

        Loan::query()
            ->where('customer_id', '!=', $loan->customer_id)
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhere('disbursement_phone_number', $candidate);
                }
            })
            ->with('customer')
            ->orderByDesc('id')
            ->get()
            ->each(function (Loan $otherLoan) use ($matches, $clearedKeys, $matchType, $credentialLabel): void {
                $customer = $otherLoan->customer;
                if (! $customer) {
                    return;
                }

                if ($this->isCleared($clearedKeys, (int) $customer->id, $matchType, $credentialLabel)) {
                    return;
                }

                $matches->push($this->formatMatch(
                    customer: $customer,
                    loan: $otherLoan,
                    source: 'loan',
                    matchReason: 'Same disbursement mobile money number on another loan',
                    matchedCredential: $credentialLabel,
                ));
            });

        CustomerPaymentDetail::query()
            ->where('customer_id', '!=', $loan->customer_id)
            ->where('method_type', 'wallet')
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhere('wallet_number', $candidate);
                }
            })
            ->with('customer')
            ->get()
            ->each(function (CustomerPaymentDetail $detail) use ($matches, $clearedKeys, $matchType, $credentialLabel): void {
                $customer = $detail->customer;
                if (! $customer) {
                    return;
                }

                if ($this->isCleared($clearedKeys, (int) $customer->id, $matchType, $credentialLabel)) {
                    return;
                }

                $matches->push($this->formatMatch(
                    customer: $customer,
                    loan: null,
                    source: 'customer_payment_profile',
                    matchReason: 'Same wallet number on another customer profile',
                    matchedCredential: $credentialLabel,
                ));
            });

        return $matches;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function matchesForBankAccount(Loan $loan, Collection $clearedKeys): Collection
    {
        $accountNumber = Str::upper(trim((string) $loan->disbursement_account_number));
        $institutionId = $loan->disbursement_financial_institution_id;
        $matchType = 'same_disbursement_bank_account';
        $matchValue = $this->bankMatchValue($institutionId, $accountNumber);
        $maskedAccount = DisbursementDestinationService::maskAccountNumber($accountNumber);

        $matches = collect();

        $loanQuery = Loan::query()
            ->where('customer_id', '!=', $loan->customer_id)
            ->whereNotNull('disbursement_account_number')
            ->whereRaw('UPPER(TRIM(disbursement_account_number)) = ?', [$accountNumber]);

        if ($institutionId) {
            $loanQuery->where('disbursement_financial_institution_id', $institutionId);
        }

        $loanQuery->with(['customer', 'disbursementFinancialInstitution'])
            ->orderByDesc('id')
            ->get()
            ->each(function (Loan $otherLoan) use ($matches, $clearedKeys, $matchType, $matchValue, $maskedAccount): void {
                $customer = $otherLoan->customer;
                if (! $customer) {
                    return;
                }

                if ($this->isCleared($clearedKeys, (int) $customer->id, $matchType, $matchValue)) {
                    return;
                }

                $institution = $otherLoan->disbursementFinancialInstitution?->name ?? 'Bank';

                $matches->push($this->formatMatch(
                    customer: $customer,
                    loan: $otherLoan,
                    source: 'loan',
                    matchReason: 'Same bank account on another loan ('.$institution.')',
                    matchedCredential: $maskedAccount,
                ));
            });

        $profileQuery = CustomerPaymentDetail::query()
            ->where('customer_id', '!=', $loan->customer_id)
            ->where('method_type', 'bank')
            ->whereNotNull('account_number')
            ->whereRaw('UPPER(TRIM(account_number)) = ?', [$accountNumber]);

        if ($institutionId) {
            $profileQuery->where('bank_financial_institution_id', $institutionId);
        }

        $profileQuery->with('customer')
            ->get()
            ->each(function (CustomerPaymentDetail $detail) use ($matches, $clearedKeys, $matchType, $matchValue, $maskedAccount): void {
                $customer = $detail->customer;
                if (! $customer) {
                    return;
                }

                if ($this->isCleared($clearedKeys, (int) $customer->id, $matchType, $matchValue)) {
                    return;
                }

                $matches->push($this->formatMatch(
                    customer: $customer,
                    loan: null,
                    source: 'customer_payment_profile',
                    matchReason: 'Same bank account on another customer profile',
                    matchedCredential: $maskedAccount,
                ));
            });

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function phoneCandidates(string $phone): array
    {
        $candidates = PhoneNumberFormatter::lookupCandidates($phone);
        $stripped = PhoneNumberFormatter::stripFormatting($phone);

        if ($stripped) {
            $candidates[] = $stripped;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function bankMatchValue(?int $institutionId, string $accountNumber): string
    {
        return ($institutionId ?? 'any').':'.$accountNumber;
    }

    private function formatMatch(
        Customer $customer,
        ?Loan $loan,
        string $source,
        string $matchReason,
        string $matchedCredential,
    ): array {
        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'customer_status' => $customer->status,
            'loan_id' => $loan?->id,
            'loan_number' => $loan?->loan_number,
            'source' => $source,
            'match_reason' => $matchReason,
            'matched_credential' => $matchedCredential,
        ];
    }

    /**
     * @return array{has_matches: bool, total_count: int, matches: list<array<string, mixed>>}
     */
    private function emptyResult(): array
    {
        return [
            'has_matches' => false,
            'total_count' => 0,
            'matches' => [],
        ];
    }
}
