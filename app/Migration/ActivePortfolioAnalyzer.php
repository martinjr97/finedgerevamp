<?php

namespace App\Migration;

use Illuminate\Support\Collection;

class ActivePortfolioAnalyzer
{
    public function __construct(
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
        private readonly LegacyProductMapper $productMapper,
        private readonly RepaymentAttributionService $attributionService,
        private readonly BankWalletAnalyzer $bankWalletAnalyzer,
    ) {}

    /**
     * @param  list<int>|null  $pilotLoanIds
     * @return array<string, mixed>
     */
    public function analyze(?array $pilotLoanIds = null): array
    {
        $db = LegacyConnection::connection();

        if ($pilotLoanIds !== null) {
            $activeLoans = $db->table('loans')->whereIn('id', $pilotLoanIds)->get()->map(fn ($r) => (array) $r);
            $userIds = $activeLoans->pluck('user_id')->unique()->values()->all();
        } else {
            $activeLoans = $db->table('loans')->where('status_code', '301')->get()->map(fn ($r) => (array) $r);
            $userIds = $activeLoans->pluck('user_id')->unique()->values()->all();
        }

        $clients = $db->table('clients')->get()->keyBy('id')->map(fn ($r) => (array) $r);
        $customers = $db->table('customers')->whereIn('user_id', $userIds)->get()->keyBy('user_id')->map(fn ($r) => (array) $r);
        $users = $db->table('users')->whereIn('id', $userIds)->get()->keyBy('id')->map(fn ($r) => (array) $r);

        $productCounts = ['mou' => 0, 'government' => 0, 'character' => 0, 'marketeer' => 0];
        $activeExposure = 0.0;
        $loanRows = [];

        foreach ($activeLoans as $loan) {
            $client = $clients->get($loan['client_id'] ?? null);
            $map = $this->productMapper->mapLoanProduct($loan, $client);
            $productCounts[$map['category']] = ($productCounts[$map['category']] ?? 0) + 1;
            $effective = $this->balanceCalculator->effectiveOutstanding($loan);
            $activeExposure += $effective;
            $loanRows[] = [
                'legacy_loan_id' => $loan['id'],
                'legacy_user_id' => $loan['user_id'],
                'product' => $map,
                'effective_outstanding' => round($effective, 2),
            ];
        }

        $multiActive = $activeLoans->groupBy('user_id')->filter(fn ($g) => $g->count() > 1)->count();

        $clientIds = $customers->pluck('client_id')->unique()->filter()->values();
        $companiesNeeded = $clients->only($clientIds->all())->count();

        $repayments = $db->table('repayments')
            ->whereIn('user_id', $userIds)
            ->where('status_code', 215)
            ->get()
            ->map(fn ($r) => (array) $r);

        $attributionCounts = [
            RepaymentAttributionService::A_DIRECT => 0,
            RepaymentAttributionService::B_RECONSTRUCTED => 0,
            RepaymentAttributionService::C_AMBIGUOUS => 0,
            RepaymentAttributionService::D_MANUAL => 0,
        ];

        foreach ($repayments as $repayment) {
            $userId = (int) $repayment['user_id'];
            $customer = $customers->get($userId);
            $client = $customer ? $clients->get($customer['client_id'] ?? null) : null;
            $activeAtPayment = $this->activeLoansForUserAt($db, $userId, $repayment['created_at']);
            $class = $this->attributionService->classify($repayment, $activeAtPayment, $client);
            $attributionCounts[$class['class']]++;
        }

        $bankWallet = $this->bankWalletAnalyzer->analyzeForUsers($userIds);

        $customerUniverse = [];
        foreach ($userIds as $userId) {
            $user = $users->get($userId);
            $customer = $customers->get($userId);
            $client = $customer ? $clients->get($customer['client_id'] ?? null) : null;
            $userActiveLoans = $activeLoans->where('user_id', $userId);
            $historicalCount = (int) $db->table('loans')->where('user_id', $userId)->count();

            $customerUniverse[] = [
                'legacy_user_id' => $userId,
                'legacy_customer_id' => $customer['id'] ?? null,
                'name' => trim(($user['fname'] ?? '').' '.($user['lname'] ?? '')),
                'nrc' => $customer['nrc'] ?? $user['nrc'] ?? null,
                'emp_number' => $user['emp_number'] ?? null,
                'phone' => $user['phone_number'] ?? null,
                'email' => $user['email'] ?? null,
                'legacy_client_id' => $customer['client_id'] ?? null,
                'company_name' => $client['company_name'] ?? null,
                'product_type' => $client['product_type'] ?? null,
                'active_loans' => $userActiveLoans->count(),
                'historical_loans' => $historicalCount,
                'active_exposure' => round($userActiveLoans->sum(fn ($l) => $this->balanceCalculator->effectiveOutstanding((array) $l)), 2),
            ];
        }

        return [
            'scope' => $pilotLoanIds ? 'pilot' : 'full_active',
            'pilot_loan_ids' => $pilotLoanIds,
            'active_customer_count' => count($userIds),
            'active_loan_count' => $activeLoans->count(),
            'product_counts' => $productCounts,
            'multi_active_customers' => $multiActive,
            'companies_needed' => $companiesNeeded,
            'total_active_exposure' => round($activeExposure, 2),
            'repayment_attribution' => $attributionCounts,
            'successful_repayments_in_scope' => $repayments->count(),
            'customers' => $customerUniverse,
            'loans' => $loanRows,
            'bank_wallet' => $bankWallet,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeLoansForUserAt($db, int $userId, string $paymentAt): array
    {
        return $db->table('loans')
            ->where('user_id', $userId)
            ->where('status_code', '301')
            ->where('created_at', '<=', $paymentAt)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
