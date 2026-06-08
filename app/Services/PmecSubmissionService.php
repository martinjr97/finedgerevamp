<?php

namespace App\Services;

use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\PmecSubmission;
use App\Models\PmecSubmissionItem;
use App\Support\PmecDateFormatter;
use App\Support\PmecSubmissionDefaults;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PmecSubmissionService
{
    /**
     * @param  array<int>|null  $customerGroupIds
     * @param  array<int>|null  $manualLoanIds
     * @return Collection<int, array<string, mixed>>
     */
    public function buildPreviewRows(
        LoanProduct $product,
        string $submissionMonth,
        string $mode,
        ?array $customerGroupIds = null,
        ?array $manualLoanIds = null,
    ): Collection {
        $loans = $this->eligibleLoansQuery($product, $customerGroupIds)
            ->with(['customer', 'customerGroup', 'paymentSchedules', 'loanProduct'])
            ->get();

        $loans = $this->filterLoansByMode($loans, $mode, $manualLoanIds);

        return $loans->map(fn (Loan $loan) => $this->buildPreviewRow($loan))->values();
    }

    /**
     * @param  array<int>|null  $customerGroupIds
     */
    public function eligibleLoansQuery(LoanProduct $product, ?array $customerGroupIds = null): Builder
    {
        if ($product->category !== 'government') {
            return Loan::query()->whereRaw('0 = 1');
        }

        $query = Loan::query()
            ->where('loan_product_id', $product->id)
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('loanProduct', fn (Builder $q) => $q->where('category', 'government'));

        if (! empty($customerGroupIds)) {
            $query->whereIn('customer_group_id', $customerGroupIds);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @param  array<int>|null  $manualLoanIds
     * @return Collection<int, Loan>
     */
    public function filterLoansByMode(Collection $loans, string $mode, ?array $manualLoanIds = null): Collection
    {
        return match ($mode) {
            PmecSubmissionDefaults::MODE_MANUAL => $loans->filter(
                fn (Loan $loan) => in_array($loan->id, $manualLoanIds ?? [], true)
            )->values(),
            PmecSubmissionDefaults::MODE_FAILED_MISSED => $loans->filter(
                fn (Loan $loan) => $this->hasFailedOrMissedSubmission($loan->id)
            )->values(),
            default => $loans->filter(
                fn (Loan $loan) => ! $this->hasSuccessfulSubmission($loan->id)
            )->values(),
        };
    }

    public function hasSuccessfulSubmission(int $loanId): bool
    {
        return PmecSubmissionItem::query()
            ->where('loan_id', $loanId)
            ->where('status', PmecSubmissionDefaults::ITEM_STATUS_SUBMITTED)
            ->exists();
    }

    public function hasFailedOrMissedSubmission(int $loanId): bool
    {
        return PmecSubmissionItem::query()
            ->where('loan_id', $loanId)
            ->where('status', PmecSubmissionDefaults::ITEM_STATUS_FAILED)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPreviewRow(Loan $loan): array
    {
        $customer = $loan->customer;
        $payload = $this->buildLoanPayload($loan);
        $errors = $this->validationErrors($payload);
        $priorStatus = $this->priorSubmissionLabel($loan->id);

        return [
            'loan_id' => $loan->id,
            'customer_id' => $loan->customer_id,
            'customer_name' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')),
            'employee_number' => $customer?->employee_number,
            'nrc' => $customer?->national_id,
            'loan_number' => $loan->loan_number,
            'group_name' => $loan->customerGroup?->name,
            'begda' => $payload['begda_formatted'],
            'endda' => $payload['endda_formatted'],
            'betrg' => $payload['betrg'],
            'submission_status' => $priorStatus,
            'validation_errors' => $errors,
            'is_valid' => $errors === [],
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLoanPayload(Loan $loan): array
    {
        $customer = $loan->customer;
        $loanStart = $loan->loan_start_date ?? $loan->first_payment_date;
        $loanEnd = $loan->loan_end_date ?? $loan->last_payment_date;

        $begda = PmecDateFormatter::begdaFromLoanStart($loanStart);
        $endda = PmecDateFormatter::enddaFromLoanEnd($loanEnd);

        return [
            'pernr' => filled($customer?->employee_number) ? (string) $customer->employee_number : null,
            'nrc' => filled($customer?->national_id) ? (string) $customer->national_id : null,
            'first_name' => filled($customer?->first_name) ? (string) $customer->first_name : null,
            'surname' => filled($customer?->last_name) ? (string) $customer->last_name : null,
            'betrg' => $this->monthlyInstallmentAmount($loan),
            'begda' => $begda,
            'endda' => $endda,
            'begda_formatted' => $begda ? PmecDateFormatter::format($begda) : null,
            'endda_formatted' => $endda ? PmecDateFormatter::format($endda) : null,
            'lgart' => PmecSubmissionDefaults::LGART,
            'emfsl' => PmecSubmissionDefaults::EMFSL,
            'zlsch' => PmecSubmissionDefaults::ZLSCH,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function validationErrors(array $payload): array
    {
        $errors = [];

        if (blank($payload['pernr'] ?? null)) {
            $errors[] = 'Employee number (PERNR) is required.';
        }
        if (blank($payload['nrc'] ?? null)) {
            $errors[] = 'NRC is required.';
        }
        if (blank($payload['first_name'] ?? null)) {
            $errors[] = 'First name is required.';
        }
        if (blank($payload['surname'] ?? null)) {
            $errors[] = 'Surname is required.';
        }
        if ($payload['betrg'] === null || (float) $payload['betrg'] <= 0) {
            $errors[] = 'Installment amount is required.';
        }
        if (! $payload['begda'] instanceof Carbon) {
            $errors[] = 'Loan start date is required for BEGDA.';
        }
        if (! $payload['endda'] instanceof Carbon) {
            $errors[] = 'Loan end date is required for ENDDA.';
        }

        return $errors;
    }

    public function monthlyInstallmentAmount(Loan $loan): ?float
    {
        if ($loan->relationLoaded('paymentSchedules')) {
            $schedule = $loan->paymentSchedules->sortBy('period_number')->first();
        } else {
            $schedule = $loan->paymentSchedules()->orderBy('period_number')->first();
        }

        if ($schedule) {
            return round((float) $schedule->expected_amount, 2);
        }

        $installment = data_get($loan->metadata, 'installment_amount');

        return $installment !== null ? round((float) $installment, 2) : null;
    }

    public function priorSubmissionLabel(int $loanId): string
    {
        if ($this->hasSuccessfulSubmission($loanId)) {
            return 'Previously submitted';
        }

        if ($this->hasFailedOrMissedSubmission($loanId)) {
            return 'Failed / missed — eligible for resubmission';
        }

        $hasGenerated = PmecSubmissionItem::query()
            ->where('loan_id', $loanId)
            ->whereIn('status', [
                PmecSubmissionDefaults::ITEM_STATUS_GENERATED,
                PmecSubmissionDefaults::ITEM_STATUS_RESUBMITTED,
            ])
            ->exists();

        return $hasGenerated ? 'Generated (not yet submitted to PMEC)' : 'Not yet submitted';
    }

    public function generateBatchNumber(string $submissionMonth): string
    {
        $prefix = 'PMEC-'.str_replace('-', '', $submissionMonth).'-';
        $latest = PmecSubmission::query()
            ->where('batch_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('batch_number');

        $sequence = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function exportFilename(string $submissionMonth): string
    {
        [$year, $month] = explode('-', $submissionMonth);

        return sprintf('PMEC_SUBMISSION_F021_%s_%s.xlsx', $year, $month);
    }

    /**
     * @param  Collection<int, PmecSubmissionItem>  $items
     * @return array<int, array<int, string|float|null>>
     */
    public function excelRows(Collection $items): array
    {
        return $items->map(function ($item) {
            $begda = $item->begda instanceof Carbon ? $item->begda : Carbon::parse($item->begda);
            $endda = $item->endda instanceof Carbon ? $item->endda : Carbon::parse($item->endda);

            return [
            $item->pernr,
            $item->lgart,
            PmecDateFormatter::format($endda),
            PmecDateFormatter::format($begda),
            (float) $item->betrg,
            $item->emfsl,
            $item->zlsch,
            $item->nrc,
            $item->first_name,
            $item->surname,
            ];
        })->all();
    }

    /**
     * @param  array<int, int>|null  $customerGroupIds
     */
    public function resolveStoredGroupId(?array $customerGroupIds): ?int
    {
        if ($customerGroupIds === null || count($customerGroupIds) !== 1) {
            return null;
        }

        return (int) $customerGroupIds[0];
    }

    /**
     * @return list<int>
     */
    public function groupsForProduct(int $loanProductId): array
    {
        return CustomerGroup::query()
            ->where('loan_product_id', $loanProductId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    /**
     * Find the most recent failed item for resubmission linking.
     */
    public function latestFailedItemId(int $loanId): ?int
    {
        return PmecSubmissionItem::query()
            ->where('loan_id', $loanId)
            ->where('status', PmecSubmissionDefaults::ITEM_STATUS_FAILED)
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $previewRows
     * @return Collection<int, array<string, mixed>>
     */
    public function filterPreviewRowsForExport(array $previewRows, bool $excludeInvalid): Collection
    {
        $rows = collect($previewRows);

        if ($excludeInvalid) {
            return $rows->filter(fn (array $row) => $row['is_valid'] ?? false)->values();
        }

        return $rows;
    }

    public function assertNoInvalidRows(Collection $rows): void
    {
        $invalid = $rows->filter(fn (array $row) => ! ($row['is_valid'] ?? false));

        if ($invalid->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'Cannot generate PMEC file while '.$invalid->count().' loan(s) have missing required fields.'
            );
        }
    }
}
