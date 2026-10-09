<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeLoanPayoutDestinationService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validateAndBuild(array $input, Employee $employee): array
    {
        $mode = $input['payout_destination_mode'] ?? null;

        if ($mode === 'employee_account') {
            $validated = Validator::make($input, [
                'payout_destination_mode' => ['required', Rule::in(['employee_account', 'alternative'])],
                'payout_employee_bank_account_id' => [
                    'required',
                    'integer',
                    Rule::exists('employee_bank_accounts', 'id')
                        ->where('employee_id', $employee->id)
                        ->where('is_active', true),
                ],
            ])->validate();

            $account = EmployeeBankAccount::query()
                ->with('financialInstitution')
                ->where('employee_id', $employee->id)
                ->findOrFail($validated['payout_employee_bank_account_id']);

            return $this->snapshotFromEmployeeAccount($account);
        }

        if ($mode === 'alternative') {
            $payoutMethod = $input['payout_method'] ?? 'bank';

            if ($payoutMethod === 'mobile_money') {
                $validated = Validator::make($input, [
                    'payout_destination_mode' => ['required', Rule::in(['employee_account', 'alternative'])],
                    'payout_method' => ['required', Rule::in(['bank', 'mobile_money'])],
                    'payout_mobile_money_provider' => ['required', 'string', 'max:100'],
                    'payout_mobile_account_name' => ['required', 'string', 'max:255'],
                    'payout_mobile_number' => ['required', 'string', 'max:50'],
                ])->validate();

                return [
                    'source' => 'alternative',
                    'payout_method' => 'mobile_money',
                    'account_type' => 'mobile_money',
                    'bank_name' => $validated['payout_mobile_money_provider'],
                    'account_name' => $validated['payout_mobile_account_name'],
                    'account_number' => $validated['payout_mobile_number'],
                    'account_number_masked' => $this->maskNumber($validated['payout_mobile_number']),
                    'captured_at' => now()->toIso8601String(),
                ];
            }

            $validated = Validator::make($input, [
                'payout_destination_mode' => ['required', Rule::in(['employee_account', 'alternative'])],
                'payout_method' => ['required', Rule::in(['bank', 'mobile_money'])],
                'payout_financial_institution_id' => ['nullable', 'integer', 'exists:financial_institutions,id'],
                'payout_bank_name' => ['nullable', 'string', 'max:255'],
                'payout_branch_name' => ['nullable', 'string', 'max:255'],
                'payout_account_name' => ['required', 'string', 'max:255'],
                'payout_account_number' => ['required', 'string', 'max:50'],
            ])->validate();

            if (empty($validated['payout_financial_institution_id']) && empty($validated['payout_bank_name'])) {
                throw ValidationException::withMessages([
                    'payout_bank_name' => 'Select a bank from the list or enter the bank name.',
                ]);
            }

            $institutionName = null;
            if (! empty($validated['payout_financial_institution_id'])) {
                $institutionName = \App\Models\FinancialInstitution::query()
                    ->find($validated['payout_financial_institution_id'])?->name;
            }

            return [
                'source' => 'alternative',
                'payout_method' => 'bank',
                'account_type' => 'bank',
                'financial_institution_id' => $validated['payout_financial_institution_id'] ?? null,
                'bank_name' => $institutionName ?? $validated['payout_bank_name'],
                'branch_name' => $validated['payout_branch_name'] ?? null,
                'account_name' => $validated['payout_account_name'],
                'account_number' => $validated['payout_account_number'],
                'account_number_masked' => $this->maskNumber($validated['payout_account_number']),
                'captured_at' => now()->toIso8601String(),
            ];
        }

        throw ValidationException::withMessages([
            'payout_destination_mode' => 'Select payment details from the employee profile or provide an alternative account.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFromEmployeeAccount(EmployeeBankAccount $account): array
    {
        $account->loadMissing('financialInstitution');

        return [
            'source' => 'employee_account',
            'employee_bank_account_id' => $account->id,
            'payout_method' => ($account->account_type ?? 'bank') === 'mobile_money' ? 'mobile_money' : 'bank',
            'account_type' => $account->account_type ?? 'bank',
            'financial_institution_id' => $account->financial_institution_id,
            'bank_name' => $account->financialInstitution?->name ?? $account->bank_name,
            'branch_name' => $account->branch_name,
            'branch_code' => $account->branch_code,
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'account_number_masked' => $account->maskedAccountNumber(),
            'is_primary' => (bool) $account->is_primary,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public function maskNumber(string $number): string
    {
        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function formatSnapshotSummary(array $snapshot, bool $maskAccount = true): ?string
    {
        if ($snapshot === []) {
            return null;
        }

        $bank = $snapshot['bank_name'] ?? '—';
        $name = $snapshot['account_name'] ?? '—';
        $number = $maskAccount
            ? ($snapshot['account_number_masked'] ?? '—')
            : ($snapshot['account_number'] ?? $snapshot['account_number_masked'] ?? '—');

        return "{$bank} · {$name} · {$number}";
    }
}
