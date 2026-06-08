<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerPaymentDetail;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Models\WalletProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentDetailsController extends Controller
{
    public function edit(Request $request): View
    {
        $customer = $request->user('customer');
        abort_unless($customer, 403);

        $customer->loadMissing('paymentDetail');
        $paymentDetail = $customer->paymentDetail;

        $isLocked = $customer->loans()
            ->whereIn('status', ['approved', 'active'])
            ->exists();

        $financialInstitutions = FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $walletProviders = WalletProvider::query()
            ->active()
            ->orderBy('name')
            ->get();

        $resolvedBankInstitutionId = $paymentDetail?->bank_financial_institution_id;
        $resolvedBankBranchId = $paymentDetail?->bank_financial_institution_branch_id;
        $resolvedWalletProviderId = $paymentDetail?->wallet_provider_id;

        if ($paymentDetail) {
            if (! $resolvedBankInstitutionId && filled($paymentDetail->bank_name)) {
                $institution = FinancialInstitution::query()
                    ->active()
                    ->where('name', (string) $paymentDetail->bank_name)
                    ->first();
                $resolvedBankInstitutionId = $institution?->id;
            }

            if (! $resolvedBankBranchId && $resolvedBankInstitutionId && filled($paymentDetail->bank_branch)) {
                $branch = FinancialInstitutionBranch::query()
                    ->active()
                    ->where('financial_institution_id', (int) $resolvedBankInstitutionId)
                    ->where('name', (string) $paymentDetail->bank_branch)
                    ->first();
                $resolvedBankBranchId = $branch?->id;
            }

            if (! $resolvedWalletProviderId && filled($paymentDetail->wallet_provider)) {
                $provider = WalletProvider::query()
                    ->active()
                    ->where('name', (string) $paymentDetail->wallet_provider)
                    ->first();
                $resolvedWalletProviderId = $provider?->id;
            }
        }

        return view('customer.payment-details.edit', [
            'customer' => $customer,
            'paymentDetail' => $paymentDetail,
            'financialInstitutions' => $financialInstitutions,
            'walletProviders' => $walletProviders,
            'resolvedBankInstitutionId' => $resolvedBankInstitutionId,
            'resolvedBankBranchId' => $resolvedBankBranchId,
            'resolvedWalletProviderId' => $resolvedWalletProviderId,
            'isLocked' => $isLocked,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer, 403);

        $isLocked = $customer->loans()
            ->whereIn('status', ['approved', 'active'])
            ->exists();

        if ($isLocked) {
            return redirect()
                ->route('customer.payment-details.edit')
                ->with('error', 'You cannot update payment details while you have an approved or active loan. Please contact support.');
        }

        $method = (string) $request->input('method_type');
        $selectedInstitutionId = $request->input('bank_financial_institution_id');
        $branchRule = Rule::exists('financial_institution_branches', 'id')
            ->whereNull('deleted_at')
            ->where('is_active', true);
        if (filled($selectedInstitutionId)) {
            $branchRule = $branchRule->where('financial_institution_id', (int) $selectedInstitutionId);
        }

        $validated = $request->validate([
            'method_type' => ['required', 'string', Rule::in(['bank', 'wallet'])],

            'bank_financial_institution_id' => [
                'nullable',
                Rule::requiredIf(fn () => $method === 'bank'),
                'integer',
                Rule::exists('financial_institutions', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'bank_financial_institution_branch_id' => [
                'nullable',
                Rule::requiredIf(fn () => $method === 'bank'),
                'integer',
                $branchRule,
            ],
            'account_name' => ['nullable', Rule::requiredIf(fn () => $method === 'bank'), 'string', 'max:255'],
            'account_number' => ['nullable', Rule::requiredIf(fn () => $method === 'bank'), 'string', 'max:50'],

            'wallet_provider_id' => [
                'nullable',
                Rule::requiredIf(fn () => $method === 'wallet'),
                'integer',
                Rule::exists('wallet_providers', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'wallet_number' => ['nullable', Rule::requiredIf(fn () => $method === 'wallet'), 'string', 'max:20'],
        ]);

        $method = (string) $validated['method_type'];

        $institution = null;
        $branch = null;
        if ($method === 'bank') {
            $institution = FinancialInstitution::query()->find((int) $validated['bank_financial_institution_id']);
            $branch = FinancialInstitutionBranch::query()->find((int) $validated['bank_financial_institution_branch_id']);

            if (
                ! $institution
                || ! $branch
                || (int) $branch->financial_institution_id !== (int) $institution->id
            ) {
                return back()
                    ->withInput()
                    ->withErrors(['bank_financial_institution_branch_id' => 'Please select a valid bank branch.']);
            }
        }

        $walletProvider = null;
        if ($method === 'wallet') {
            $walletProvider = WalletProvider::query()->find((int) $validated['wallet_provider_id']);
            if (! $walletProvider) {
                return back()
                    ->withInput()
                    ->withErrors(['wallet_provider_id' => 'Please select a valid wallet provider.']);
            }
        }

        CustomerPaymentDetail::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'method_type' => $method,

                'bank_financial_institution_id' => $method === 'bank' ? $institution->id : null,
                'bank_financial_institution_branch_id' => $method === 'bank' ? $branch->id : null,
                'bank_name' => $method === 'bank' ? Str::upper($institution->name) : null,
                'bank_branch' => $method === 'bank' ? Str::upper($branch->name) : null,
                'account_name' => $method === 'bank' ? Str::upper(trim((string) $validated['account_name'])) : null,
                'account_number' => $method === 'bank' ? Str::upper(trim((string) $validated['account_number'])) : null,

                'wallet_provider_id' => $method === 'wallet' ? $walletProvider->id : null,
                'wallet_provider' => $method === 'wallet' ? Str::upper($walletProvider->name) : null,
                'wallet_number' => $method === 'wallet' ? Str::upper(trim((string) $validated['wallet_number'])) : null,
            ]
        );

        return redirect()
            ->route('customer.payment-details.edit')
            ->with('status', 'Payment details updated successfully.');
    }
}

