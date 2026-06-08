@extends('layouts.customer')

@section('title', 'Payment Details')

@section('content')
    @php
        $paymentDetail = $paymentDetail ?? null;
        $methodType = old('method_type', $paymentDetail?->method_type ?? 'bank');
        $selectedFinancialInstitutionId = (string) old('bank_financial_institution_id', $resolvedBankInstitutionId ?? $paymentDetail?->bank_financial_institution_id);
        $selectedFinancialInstitutionBranchId = (string) old('bank_financial_institution_branch_id', $resolvedBankBranchId ?? $paymentDetail?->bank_financial_institution_branch_id);
        $selectedWalletProviderId = (string) old('wallet_provider_id', $resolvedWalletProviderId ?? $paymentDetail?->wallet_provider_id);
        $inputClass = 'w-full rounded-2xl bg-white border border-slate-200 text-slate-900 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500/30 focus:outline-none transition';
        $panelClass = 'bg-slate-50 border border-slate-200 rounded-2xl p-5 shadow-sm';
    @endphp

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-8">
        <div class="bg-white dark:bg-gray-800/50 rounded-2xl border border-slate-200 dark:border-slate-600 shadow-xl overflow-hidden">
            <div class="p-4 sm:p-6 space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">Payment Details</h1>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Set your default bank or wallet payment information.</p>
                    </div>
                    <a href="{{ route('customer.profile') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back
                    </a>
                </div>

                @if(session('status'))
                    <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ session('status') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="{{ $panelClass }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Current Default Payment</h2>
                            <p class="text-sm text-slate-600">This is used as your default payment information.</p>
                        </div>
                        @if($paymentDetail)
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentDetail->method_type === 'wallet' ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                                {{ $paymentDetail->method_type === 'wallet' ? 'Wallet' : 'Bank' }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 text-sm">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                            <span class="text-slate-600">Method</span>
                            <span class="font-semibold text-slate-900">{{ $paymentDetail?->method_type ? ucfirst($paymentDetail->method_type) : '—' }}</span>
                        </div>

                        @if(($paymentDetail?->method_type ?? '') === 'wallet')
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="text-slate-600">Provider</span>
                                <span class="font-semibold text-slate-900">{{ $paymentDetail->wallet_provider ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="text-slate-600">Wallet number</span>
                                <span class="font-semibold text-slate-900">{{ $paymentDetail->wallet_number ?: '—' }}</span>
                            </div>
                        @elseif(($paymentDetail?->method_type ?? '') === 'bank')
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="text-slate-600">Bank</span>
                                <span class="font-semibold text-slate-900">{{ $paymentDetail->bank_name ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="text-slate-600">Branch</span>
                                <span class="font-semibold text-slate-900">{{ $paymentDetail->bank_branch ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="text-slate-600">Account name</span>
                                <span class="font-semibold text-slate-900">{{ $paymentDetail->account_name ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <span class="text-slate-600">Account number</span>
                                <span class="font-semibold text-slate-900">{{ $paymentDetail->account_number ?: '—' }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if($isLocked)
                    <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-semibold">Payment details are locked</p>
                        <p class="mt-1 text-xs text-amber-800">
                            You cannot edit your payment details while you have an approved or active loan. Please contact support or visit an office for assistance.
                        </p>
                    </div>
                @else
                    <div class="{{ $panelClass }}">
                        <h2 class="text-lg font-semibold text-slate-900">Update Payment Details</h2>
                        <p class="text-sm text-slate-600">Please double-check account numbers before saving.</p>

                        @if ($errors->any())
                            <div class="mt-4 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                <p class="font-semibold mb-1">Please fix the errors below and try again.</p>
                                <ul class="list-disc list-inside space-y-0.5">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('customer.payment-details.update') }}" class="mt-5 space-y-6">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="method_type" class="block text-sm font-medium text-slate-700 mb-2">
                                    Payment Method <span class="text-rose-600">*</span>
                                </label>
                                <select id="method_type" name="method_type" required class="{{ $inputClass }}">
                                    <option value="bank" @selected($methodType === 'bank')>Bank</option>
                                    <option value="wallet" @selected($methodType === 'wallet')>Wallet</option>
                                </select>
                                @error('method_type')
                                    <p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="bank_fields" class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-slate-900">Bank details</p>
                                    <span class="text-xs text-slate-500">Required when method is Bank</span>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="bank_financial_institution_id" class="block text-sm font-medium text-slate-700 mb-2">Bank <span class="text-rose-600">*</span></label>
                                        <select id="bank_financial_institution_id" name="bank_financial_institution_id" class="{{ $inputClass }}" @disabled($financialInstitutions->isEmpty())>
                                            <option value="">Select bank</option>
                                            @foreach($financialInstitutions as $institution)
                                                <option value="{{ $institution->id }}" @selected($selectedFinancialInstitutionId === (string) $institution->id)>
                                                    {{ $institution->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('bank_financial_institution_id')<p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="bank_financial_institution_branch_id" class="block text-sm font-medium text-slate-700 mb-2">Branch <span class="text-rose-600">*</span></label>
                                        <select id="bank_financial_institution_branch_id" name="bank_financial_institution_branch_id" class="{{ $inputClass }}" @disabled($financialInstitutions->isEmpty())>
                                            <option value="">Select branch</option>
                                            @foreach($financialInstitutions as $institution)
                                                @foreach($institution->branches as $branch)
                                                    <option
                                                        value="{{ $branch->id }}"
                                                        data-financial-institution-id="{{ $institution->id }}"
                                                        @selected($selectedFinancialInstitutionBranchId === (string) $branch->id)
                                                    >
                                                        {{ $branch->name }}
                                                    </option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                        @error('bank_financial_institution_branch_id')<p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="account_name" class="block text-sm font-medium text-slate-700 mb-2">Account name <span class="text-rose-600">*</span></label>
                                        <input id="account_name" name="account_name" type="text" value="{{ old('account_name', $paymentDetail?->account_name ?? '') }}" class="{{ $inputClass }}" maxlength="255">
                                        @error('account_name')<p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="account_number" class="block text-sm font-medium text-slate-700 mb-2">Account number <span class="text-rose-600">*</span></label>
                                        <input id="account_number" name="account_number" type="text" value="{{ old('account_number', $paymentDetail?->account_number ?? '') }}" class="{{ $inputClass }}" maxlength="50">
                                        @error('account_number')<p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>

                            <div id="wallet_fields" class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-slate-900">Wallet details</p>
                                    <span class="text-xs text-slate-500">Required when method is Wallet</span>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="wallet_provider_id" class="block text-sm font-medium text-slate-700 mb-2">Wallet provider <span class="text-rose-600">*</span></label>
                                        <select id="wallet_provider_id" name="wallet_provider_id" class="{{ $inputClass }}" @disabled($walletProviders->isEmpty())>
                                            <option value="">Select provider</option>
                                            @foreach($walletProviders as $provider)
                                                <option value="{{ $provider->id }}" @selected($selectedWalletProviderId === (string) $provider->id)>
                                                    {{ $provider->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('wallet_provider_id')<p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="wallet_number" class="block text-sm font-medium text-slate-700 mb-2">Wallet number <span class="text-rose-600">*</span></label>
                                        <input id="wallet_number" name="wallet_number" type="text" value="{{ old('wallet_number', $paymentDetail?->wallet_number ?? '') }}" class="{{ $inputClass }}" maxlength="20">
                                        @error('wallet_number')<p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 shadow-md hover:shadow-lg transition border border-green-700/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Save Payment Details
                                </button>
                                <a href="{{ route('customer.profile') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const methodSelect = document.getElementById('method_type');
            const bankFields = document.getElementById('bank_fields');
            const walletFields = document.getElementById('wallet_fields');

            if (!methodSelect || !bankFields || !walletFields) {
                return;
            }

            const toggleSection = (section, isActive) => {
                section.classList.toggle('hidden', !isActive);
                section.querySelectorAll('input, select, textarea').forEach((el) => {
                    el.disabled = !isActive;
                });
            };

            const sync = () => {
                const method = methodSelect.value;
                toggleSection(bankFields, method === 'bank');
                toggleSection(walletFields, method === 'wallet');
            };

            methodSelect.addEventListener('change', sync);
            sync();
        })();

        (() => {
            const bankSelect = document.getElementById('bank_financial_institution_id');
            const branchSelect = document.getElementById('bank_financial_institution_branch_id');

            if (!bankSelect || !branchSelect) {
                return;
            }

            const syncBranches = () => {
                const institutionId = bankSelect.value;
                let hasVisible = false;

                branchSelect.querySelectorAll('option[data-financial-institution-id]').forEach((option) => {
                    const matches = option.dataset.financialInstitutionId === institutionId;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (matches) {
                        hasVisible = true;
                    }
                });

                if (!hasVisible) {
                    branchSelect.value = '';
                } else if (branchSelect.selectedOptions[0]?.disabled) {
                    branchSelect.value = '';
                }
            };

            bankSelect.addEventListener('change', syncBranches);
            syncBranches();
        })();
    </script>
@endpush

