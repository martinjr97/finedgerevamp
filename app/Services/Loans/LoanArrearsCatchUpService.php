<?php

namespace App\Services\Loans;

use App\Models\Admin;
use App\Models\Loan;
use Carbon\Carbon;

class LoanArrearsCatchUpService
{
    public const PROMPT_STATUS_DISMISSED = 'dismissed';

    public const PROMPT_STATUS_APPLIED = 'applied';

    public function __construct(
        private readonly LoanArrearsAccrualService $accrualService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function buildPromptForLoan(Loan $loan): ?array
    {
        if ($this->isPromptPermanentlyDismissed($loan)) {
            return null;
        }

        if (! $this->loanIsEligibleForCatchUp($loan)) {
            return null;
        }

        $preview = $this->accrualService->previewMissedAccruals($loan);
        if (! $preview['has_missed_accruals']) {
            return null;
        }

        return $preview;
    }

    public function isPromptPermanentlyDismissed(Loan $loan): bool
    {
        return data_get($loan->metadata, 'arrears_catchup_prompt.status') === self::PROMPT_STATUS_DISMISSED;
    }

    public function dismissPrompt(Loan $loan, Admin $admin, ?array $preview = null): void
    {
        $metadata = $loan->metadata ?? [];
        $metadata['arrears_catchup_prompt'] = [
            'status' => self::PROMPT_STATUS_DISMISSED,
            'dismissed_at' => now()->toIso8601String(),
            'dismissed_by_admin_id' => $admin->id,
            'preview_snapshot' => $preview,
        ];

        $loan->update(['metadata' => $metadata]);
    }

    /**
     * @return array{preview: array<string, mixed>, accrual_result: array<string, mixed>}
     */
    public function applyCatchUp(Loan $loan, Admin $admin): array
    {
        $preview = $this->accrualService->previewMissedAccruals($loan);
        if (! $preview['has_missed_accruals']) {
            throw new \InvalidArgumentException('There are no missed arrears accruals to apply for this loan.');
        }

        $through = Carbon::parse($preview['through_date'], config('arrears.timezone', 'Africa/Lusaka'))->startOfDay();
        $result = $this->accrualService->accrueLoan($loan, $through, null, null, false);

        $metadata = $loan->fresh()->metadata ?? [];
        $metadata['arrears_catchup_prompt'] = [
            'status' => self::PROMPT_STATUS_APPLIED,
            'applied_at' => now()->toIso8601String(),
            'applied_by_admin_id' => $admin->id,
            'preview_snapshot' => $preview,
            'accrual_result' => $result,
        ];

        $loan->update(['metadata' => $metadata]);

        return [
            'preview' => $preview,
            'accrual_result' => $result,
        ];
    }

    public function loanIsEligibleForCatchUp(Loan $loan): bool
    {
        if (in_array($loan->status, ['settled', 'completed', 'cancelled', 'pending_approval'], true)) {
            return false;
        }

        if ($loan->arrear_rate === null || (float) $loan->arrear_rate <= 0) {
            return false;
        }

        if ($loan->paymentSchedules()->doesntExist()) {
            return false;
        }

        $today = Carbon::today(config('arrears.timezone', 'Africa/Lusaka'));
        if ($today->gt($this->accrualService->resolveNplCutoffDate($loan))) {
            return false;
        }

        return true;
    }
}
