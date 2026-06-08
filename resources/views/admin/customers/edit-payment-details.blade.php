@extends('layouts.admin')

@section('title', 'Edit Payment Details | '.config('app.system_name'))

	@section('content')
	    @php
	        $paymentDetail = $customer->paymentDetail;
	        $methodType = old('method_type', $paymentDetail->method_type ?? 'bank');
	        $financialInstitutions = $financialInstitutions ?? collect();
	        $walletProviders = $walletProviders ?? collect();
	        $selectedFinancialInstitutionId = (string) old('bank_financial_institution_id', $resolvedBankInstitutionId ?? $paymentDetail?->bank_financial_institution_id);
	        $selectedFinancialInstitutionBranchId = (string) old('bank_financial_institution_branch_id', $resolvedBankBranchId ?? $paymentDetail?->bank_financial_institution_branch_id);
	        $selectedWalletProviderId = (string) old('wallet_provider_id', $resolvedWalletProviderId ?? $paymentDetail?->wallet_provider_id);
	        $inputClass = 'w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-purple-500 focus:ring-purple-500/40 focus:outline-none transition';
	    @endphp

    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Customer Management</p>
                <h1 class="text-3xl font-bold">Edit Payment Details</h1>
                <p class="text-sm text-slate-400">
                    Customer: <span class="font-semibold text-white">{{ $customer->full_name }}</span>
                    <span class="text-slate-500">•</span>
                    ID: <span class="font-semibold text-white">{{ $customer->id }}</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Customer
                </a>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <div>
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-purple-500"></span>
                    Default Payment Information
                </h2>
                <p class="text-sm text-slate-400 mt-1">Set the customer's default payment method (bank or wallet).</p>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                    <p class="font-semibold mb-1">Please fix the errors below and try again.</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.customers.payment-details.update', $customer) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="method_type" class="block text-sm font-medium text-slate-300 mb-2">
                        Payment Method <span class="text-red-400">*</span>
                    </label>
                    <select id="method_type" name="method_type" required class="{{ $inputClass }}">
                        <option value="bank" @selected($methodType === 'bank')>Bank</option>
                        <option value="wallet" @selected($methodType === 'wallet')>Wallet</option>
                    </select>
                    @error('method_type')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

	                <div id="bank_fields" class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-4" data-payment-bank-branch-fields>
	                    <div class="flex items-center justify-between">
	                        <p class="text-sm font-semibold text-white">Bank details</p>
	                        <span class="text-xs text-slate-400">Required when method is Bank</span>
	                    </div>

	                    <div class="grid gap-4 md:grid-cols-2">
	                        <div>
	                            <label for="bank_financial_institution_id" class="block text-sm font-medium text-slate-300 mb-2">Bank <span class="text-red-400">*</span></label>
	                            <select id="bank_financial_institution_id" name="bank_financial_institution_id" data-no-select-search="true" data-bank-institution-id-select class="{{ $inputClass }}" @disabled($financialInstitutions->isEmpty())>
	                                <option value="">Select bank</option>
	                                @foreach($financialInstitutions as $institution)
	                                    <option value="{{ $institution->id }}" @selected($selectedFinancialInstitutionId === (string) $institution->id)>
	                                        {{ $institution->name }}
	                                    </option>
	                                @endforeach
	                            </select>
	                            @error('bank_financial_institution_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
	                        </div>
	                        <div>
	                            <label for="bank_financial_institution_branch_id" class="block text-sm font-medium text-slate-300 mb-2">Branch <span class="text-red-400">*</span></label>
	                            <select id="bank_financial_institution_branch_id" name="bank_financial_institution_branch_id" data-no-select-search="true" data-bank-branch-id-select data-institutions-empty="{{ $financialInstitutions->isEmpty() ? '1' : '0' }}" class="{{ $inputClass }}" @disabled($financialInstitutions->isEmpty())>
	                                <option value="">@if($financialInstitutions->isEmpty()) No branches available @else Select bank first @endif</option>
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
	                            @error('bank_financial_institution_branch_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
	                            @if($financialInstitutions->isEmpty())
	                                <p class="mt-1 text-xs text-amber-300">
	                                    No banks configured.
	                                    <a href="{{ route('admin.financial-institutions.create') }}" class="underline hover:text-amber-200">Add a financial institution</a>
	                                    and branches first.
	                                </p>
	                            @else
	                                <p class="mt-1 text-xs text-slate-400">Branches update when you select a bank.</p>
	                            @endif
	                        </div>
	                        <div>
	                            <label for="account_name" class="block text-sm font-medium text-slate-300 mb-2">Account name <span class="text-red-400">*</span></label>
	                            <input id="account_name" name="account_name" type="text" value="{{ old('account_name', $paymentDetail->account_name ?? '') }}" class="{{ $inputClass }}" maxlength="255">
                            @error('account_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="account_number" class="block text-sm font-medium text-slate-300 mb-2">Account number <span class="text-red-400">*</span></label>
                            <input id="account_number" name="account_number" type="text" value="{{ old('account_number', $paymentDetail->account_number ?? '') }}" class="{{ $inputClass }}" maxlength="50">
                            @error('account_number')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

	                <div id="wallet_fields" class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-4">
	                    <div class="flex items-center justify-between">
	                        <p class="text-sm font-semibold text-white">Wallet details</p>
	                        <span class="text-xs text-slate-400">Required when method is Wallet</span>
	                    </div>

	                    <div class="grid gap-4 md:grid-cols-2">
	                        <div>
	                            <label for="wallet_provider_id" class="block text-sm font-medium text-slate-300 mb-2">Wallet provider <span class="text-red-400">*</span></label>
	                            <select id="wallet_provider_id" name="wallet_provider_id" class="{{ $inputClass }}" @disabled($walletProviders->isEmpty())>
	                                <option value="">Select provider</option>
	                                @foreach($walletProviders as $provider)
	                                    <option value="{{ $provider->id }}" @selected($selectedWalletProviderId === (string) $provider->id)>
	                                        {{ $provider->name }}
	                                    </option>
	                                @endforeach
	                            </select>
	                            @error('wallet_provider_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
	                        </div>
	                        <div>
	                            <label for="wallet_number" class="block text-sm font-medium text-slate-300 mb-2">Wallet number <span class="text-red-400">*</span></label>
	                            <input id="wallet_number" name="wallet_number" type="text" value="{{ old('wallet_number', $paymentDetail->wallet_number ?? '') }}" class="{{ $inputClass }}" maxlength="20">
                            @error('wallet_number')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-purple-500 to-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-purple-500/30 hover:shadow-purple-500/50 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Payment Details
                    </button>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-6 py-3 text-sm text-white hover:bg-white/10 transition">
                        Cancel
                    </a>
                </div>
            </form>
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
                if (method === 'bank' && typeof window.initPaymentBankBranchCascade === 'function') {
                    window.initPaymentBankBranchCascade();
                }
            };

	            methodSelect.addEventListener('change', sync);
	            sync();
	        })();
	    </script>
	    @include('partials.payment-bank-branch-cascade-script')
	@endpush
