<?php

namespace App\Migration;

class RepaymentAttributionService
{
    public const A_DIRECT = 'A_DIRECT';

    public const B_RECONSTRUCTED = 'B_RECONSTRUCTED';

    public const C_AMBIGUOUS = 'C_AMBIGUOUS';

    public const D_MANUAL = 'D_MANUAL';

    public function __construct(
        private readonly LegacyProductMapper $productMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $repayment
     * @param  array<int, array<string, mixed>>  $activeLoansAtPayment
     * @param  array<string, mixed>|null  $client
     */
    public function classify(array $repayment, array $activeLoansAtPayment, ?array $client = null): array
    {
        if ((int) ($repayment['status_code'] ?? 0) !== 215) {
            return ['class' => self::D_MANUAL, 'reason' => 'not_successful', 'allocations' => []];
        }

        if ($this->hasPopulatedAffectedLoanIds($repayment)) {
            return [
                'class' => self::A_DIRECT,
                'reason' => 'affected_loan_ids populated',
                'allocations' => $this->parseAffectedLoanIds($repayment),
            ];
        }

        $productType = $client['product_type'] ?? null;
        $mouLoans = array_values(array_filter($activeLoansAtPayment, fn ($l) => $this->isMouLoan($l, $client)));
        $characterLoans = array_values(array_filter($activeLoansAtPayment, fn ($l) => ! $this->isMouLoan($l, $client) && ($productType === 'character_based' || ! $this->isMarketizeLoan($l, $client))));
        $marketizeLoans = array_values(array_filter($activeLoansAtPayment, fn ($l) => $this->isMarketizeLoan($l, $client)));

        if ($productType === 'marketize_based' || count($marketizeLoans) === 1) {
            return ['class' => self::B_RECONSTRUCTED, 'reason' => 'marketize schedule path', 'allocations' => []];
        }

        if ($productType === 'character_based' || (count($characterLoans) > 0 && count($mouLoans) === 0)) {
            return ['class' => self::B_RECONSTRUCTED, 'reason' => 'character due-date waterfall', 'allocations' => []];
        }

        if (count($mouLoans) <= 1) {
            return ['class' => self::B_RECONSTRUCTED, 'reason' => 'single active MOU loan', 'allocations' => []];
        }

        return [
            'class' => self::C_AMBIGUOUS,
            'reason' => 'multiple active MOU loans without affected_loan_ids',
            'allocations' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $repayment
     */
    public function hasPopulatedAffectedLoanIds(array $repayment): bool
    {
        $raw = $repayment['affected_loan_ids'] ?? null;
        if ($raw === null || $raw === '' || $raw === 'null' || $raw === '[]') {
            return false;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded) || $decoded === []) {
            return false;
        }

        foreach ($decoded as $item) {
            if (! empty($item['loan_id']) && ! empty($item['amount_applied'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $repayment
     * @return list<array{loan_id: int, amount_applied: float|null}>
     */
    public function parseAffectedLoanIds(array $repayment): array
    {
        $raw = $repayment['affected_loan_ids'] ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $item) {
            $out[] = [
                'loan_id' => (int) ($item['loan_id'] ?? 0),
                'amount_applied' => isset($item['amount_applied']) ? (float) $item['amount_applied'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $loan
     * @param  array<string, mixed>|null  $client
     */
    public function isMouLoan(array $loan, ?array $client): bool
    {
        return (bool) ($loan['salary_based'] ?? false)
            || (bool) ($loan['gvnt_loan'] ?? false)
            || (($client['product_type'] ?? null) === 'salary_based');
    }

    /**
     * @param  array<string, mixed>  $loan
     * @param  array<string, mixed>|null  $client
     */
    public function isMarketizeLoan(array $loan, ?array $client): bool
    {
        return ($client['product_type'] ?? null) === 'marketize_based';
    }
}
