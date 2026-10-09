<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPaymentSchedule;
use App\Services\LoanPricingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeLoanScheduleService
{
    public function __construct(
        private readonly LoanPricingService $loanPricingService,
        private readonly EmployeeLoanPricingService $employeeLoanPricingService,
    ) {}

    /**
     * @return array{
     *     pricing: array<string, mixed>,
     *     loan_start_date: string,
     *     first_payment_date: string|null,
     *     loan_end_date: string|null,
     *     installment_amount: float|null,
     *     repayment_schedule: array<int, array<string, mixed>>
     * }
     */
    public function previewForDraftLoan(EmployeeLoan $loan): array
    {
        return $this->previewFromInput([
            'loan_rate_id' => (int) $loan->loan_rate_id,
            'principal_amount' => (float) $loan->principal_amount,
            'tenure_months' => (int) $loan->tenure_months,
            'repayment_frequency' => $loan->repayment_frequency ?? 'monthly',
            'first_payment_date' => $loan->first_payment_date?->toDateString(),
            'application_date' => $loan->application_date?->toDateString(),
        ]);
    }

    /**
     * @param  array{
     *     loan_rate_id: int,
     *     principal_amount: float|int|string,
     *     tenure_months: int,
     *     repayment_frequency?: string,
     *     first_payment_date?: string|null,
     *     application_date?: string|null
     * }  $input
     * @return array{
     *     pricing: array<string, mixed>,
     *     loan_start_date: string,
     *     first_payment_date: string|null,
     *     loan_end_date: string|null,
     *     installment_amount: float|null,
     *     repayment_schedule: array<int, array<string, mixed>>
     * }
     */
    public function previewFromInput(array $input): array
    {
        $pricing = $this->employeeLoanPricingService->preview([
            'loan_rate_id' => (int) $input['loan_rate_id'],
            'principal' => (float) $input['principal_amount'],
            'tenure_months' => (int) $input['tenure_months'],
        ]);

        $frequency = $input['repayment_frequency'] ?? 'monthly';
        $firstPayment = ! empty($input['first_payment_date'])
            ? Carbon::parse($input['first_payment_date'])
            : null;
        $scheduleRows = [];

        foreach ($pricing['installments'] as $row) {
            $period = (int) ($row['period'] ?? 0);
            $periodIndex = max(0, $period - 1);
            $dueDate = null;

            if ($firstPayment) {
                $dueDate = $frequency === 'weekly'
                    ? $firstPayment->copy()->addWeeks($periodIndex)
                    : $firstPayment->copy()->addMonths($periodIndex);
            }

            $scheduleRows[] = [
                'period_number' => $period,
                'due_date' => $dueDate,
                'expected_amount' => (float) ($row['expected_amount'] ?? 0),
                'principal_component' => (float) ($row['principal_component'] ?? 0),
                'interest_component' => (float) ($row['interest_component'] ?? 0),
                'fee_component' => (float) ($row['fee_component'] ?? 0),
            ];
        }

        $lastDue = $scheduleRows !== [] ? ($scheduleRows[array_key_last($scheduleRows)]['due_date'] ?? null) : null;
        $loanStart = ! empty($input['application_date'])
            ? Carbon::parse($input['application_date'])
            : now();

        return [
            'pricing' => $pricing,
            'loan_start_date' => $loanStart->toDateString(),
            'first_payment_date' => $firstPayment?->toDateString(),
            'loan_end_date' => $lastDue instanceof Carbon ? $lastDue->toDateString() : ($lastDue ? Carbon::parse($lastDue)->toDateString() : null),
            'installment_amount' => isset($pricing['installment_amount']) ? (float) $pricing['installment_amount'] : null,
            'repayment_schedule' => $scheduleRows,
        ];
    }

    public function generateForLoan(EmployeeLoan $loan): void
    {
        if (! $loan->first_payment_date || $loan->tenure_months <= 0) {
            return;
        }

        if ($loan->paymentSchedules()->exists()) {
            return;
        }

        $plan = $this->resolveSchedulePlan($loan);
        $installments = $this->loanPricingService->calculateComponentInstallments(
            $plan['principal'],
            $plan['processing_fee'],
            $plan['interest'],
            (int) $loan->tenure_months,
        )['installments'];

        $today = Carbon::today();
        $frequency = $loan->repayment_frequency ?? 'monthly';

        DB::transaction(function () use ($loan, $installments, $today, $frequency, $plan) {
            foreach ($installments as $row) {
                $period = (int) $row['period'];
                $periodIndex = $period - 1;

                if ($frequency === 'weekly') {
                    $dueDate = $loan->first_payment_date->copy()->addWeeks($periodIndex);
                } else {
                    $dueDate = $loan->first_payment_date->copy()->addMonths($periodIndex);
                }

                $expectedAmount = (float) $row['expected_amount'];
                $status = 'upcoming';
                $daysOverdue = 0;

                if ($dueDate->isPast()) {
                    $status = 'overdue';
                    $daysOverdue = max(0, $today->diffInDays($dueDate));
                }

                EmployeeLoanPaymentSchedule::create([
                    'employee_loan_id' => $loan->id,
                    'period_number' => $period,
                    'due_date' => $dueDate,
                    'principal_component' => (float) $row['principal_component'],
                    'interest_component' => (float) $row['interest_component'],
                    'fee_component' => (float) $row['fee_component'],
                    'expected_amount' => $expectedAmount,
                    'amount_paid' => 0,
                    'remaining_amount' => $expectedAmount,
                    'status' => $status,
                    'days_overdue' => $daysOverdue,
                    'metadata' => [
                        'schedule_basis' => $plan['schedule_basis'],
                        'is_projected_interest' => $plan['is_projected_interest'],
                    ],
                ]);
            }

            if (! $loan->loan_end_date) {
                $lastDue = $loan->paymentSchedules()->max('due_date');
                if ($lastDue) {
                    $loan->update(['loan_end_date' => $lastDue]);
                }
            }
        });
    }

    /**
     * @return array{principal: float, processing_fee: float, interest: float, schedule_basis: string, is_projected_interest: bool}
     */
    public function resolveSchedulePlan(EmployeeLoan $loan): array
    {
        $meta = $loan->metadata ?? [];
        $pricingMeta = $meta['pricing_quote'] ?? null;

        if (is_array($pricingMeta)) {
            $snapshot = $this->employeeLoanPricingService->buildEmployeeLoanSnapshot($pricingMeta);

            return $this->employeeLoanPricingService->buildSchedulePlan($pricingMeta, $snapshot);
        }

        return [
            'principal' => (float) $loan->principal_amount,
            'processing_fee' => (float) $loan->processing_fee,
            'interest' => (float) $loan->interest_accrued,
            'schedule_basis' => data_get($meta, 'pricing_metadata.schedule_basis', 'booked_total'),
            'is_projected_interest' => (bool) data_get($meta, 'pricing_metadata.schedule_uses_projected_interest', false),
        ];
    }

    public function applyPaymentToSchedule(EmployeeLoan $loan, float $scheduleAppliedAmount): void
    {
        if ($scheduleAppliedAmount <= 0) {
            return;
        }

        $remaining = $scheduleAppliedAmount;
        $schedules = $loan->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('period_number')
            ->orderBy('id')
            ->get();

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min((float) $schedule->remaining_amount, $remaining);
            $schedule->amount_paid = round((float) $schedule->amount_paid + $apply, 2);
            $schedule->remaining_amount = round((float) $schedule->remaining_amount - $apply, 2);
            $remaining -= $apply;

            $schedule->refreshStatus();
            $schedule->save();
        }
    }

    public function refreshAging(?Carbon $asOf = null): int
    {
        $asOf ??= Carbon::today();
        $updated = 0;

        EmployeeLoanPaymentSchedule::query()
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', $asOf)
            ->chunkById(200, function ($schedules) use ($asOf, &$updated) {
                foreach ($schedules as $schedule) {
                    $days = max(0, $schedule->due_date->diffInDays($asOf));
                    $schedule->update([
                        'status' => 'overdue',
                        'days_overdue' => $days,
                    ]);
                    $updated++;
                }
            });

        EmployeeLoanPaymentSchedule::query()
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '>=', $asOf)
            ->where('status', 'overdue')
            ->update(['status' => 'upcoming', 'days_overdue' => 0]);

        return $updated;
    }
}
