@extends('layouts.customer')

@section('title', 'Select Repayment Type')

@section('content')
    <style>
        input[name="repayment_type"][value="overdue"]:checked + .repayment-type-option-card .repayment-type-option-indicator {
            border-color: #ef4444 !important;
            background-color: #ef4444 !important;
        }

        input[name="repayment_type"][value="partial"]:checked + .repayment-type-option-card .repayment-type-option-indicator {
            border-color: #2563eb !important;
            background-color: #2563eb !important;
        }

        input[name="repayment_type"][value="full"]:checked + .repayment-type-option-card .repayment-type-option-indicator {
            border-color: #22c55e !important;
            background-color: #22c55e !important;
        }

        input[name="repayment_type"]:checked + .repayment-type-option-card .repayment-type-option-check {
            opacity: 1 !important;
            color: #f8f9fa !important;
        }

        input[name="amount"]::-webkit-outer-spin-button,
        input[name="amount"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[name="amount"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <div class="space-y-6 max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="bg-primary rounded-2xl p-6 shadow-xl border border-muted">
            <h1 class="text-3xl font-bold mb-2 text-white">Make a Repayment</h1>
            <p class="text-slate-200">Choose how you want to repay your loan</p>
        </div>

        {{-- Total Outstanding Balance --}}
        <div class="card rounded-2xl p-6 shadow-lg">
            <div class="text-center mb-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Booked Outstanding Balance</p>
                <p class="text-4xl font-bold text-primary">ZMW {{ number_format($totalOutstandingBalance, 2) }}</p>
            </div>
            @if($hasOverdue && $totalOverdueAmount > 0)
                <div class="bg-red-50 dark:bg-red-900/30 border-2 border-red-300 dark:border-red-600 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wider text-red-700 dark:text-red-300 mb-1 font-semibold">Overdue Amount</p>
                    <p class="text-lg font-bold text-red-900 dark:text-white">ZMW {{ number_format($totalOverdueAmount, 2) }}</p>
                </div>
            @endif
        </div>

        {{-- Repayment Type Selection Form --}}
        <form action="{{ route('customer.repayments.store-type') }}" method="POST" class="space-y-4" id="repaymentForm">
            @csrf

            {{-- Full Payment Option --}}
            <label class="block cursor-pointer">
                <input type="radio" name="repayment_type" value="full" 
                       class="peer sr-only" 
                       required
                       @if(old('repayment_type') == 'full' || old('repayment_type') == null) checked @endif>
                <div class="repayment-type-option-card bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-xl p-5 shadow-md hover:shadow-lg transition-all peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/30 dark:peer-checked:border-green-400">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                Clear All Loans
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                                Pay the full outstanding balance on all your loans
                            </p>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total amount to pay:</p>
                                <p class="text-xl font-bold text-green-600 dark:text-green-400">ZMW {{ number_format($totalOutstandingBalance, 2) }}</p>
                            </div>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <div class="repayment-type-option-indicator w-6 h-6 rounded-full border-2 border-gray-400 dark:border-gray-500 flex items-center justify-center transition-all">
                                <svg class="repayment-type-option-check w-4 h-4 opacity-0 transition-opacity" style="color: #F8F9FA !important;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </label>

            {{-- Partial Payment Option --}}
            <label class="block cursor-pointer">
                <input type="radio" name="repayment_type" value="partial" 
                       class="peer sr-only" 
                       required
                       @if(old('repayment_type') == 'partial') checked @endif>
                <div class="repayment-type-option-card bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-xl p-5 shadow-md hover:shadow-lg transition-all peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/30 dark:peer-checked:border-blue-400">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Partial Payment
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                                Pay a specific amount towards your loan(s)
                            </p>
                            <div id="partialPaymentFields" class="{{ old('repayment_type') != 'partial' && (!$hasOverdue || old('repayment_type') == 'overdue' || old('repayment_type') == 'full') ? 'hidden' : '' }} space-y-3">
                                @php
                                    $selectedLoanId = old('loan_id');
                                    $selectedLoan = $selectedLoanId ? $activeLoans->firstWhere('id', (int) $selectedLoanId) : null;
                                    $partialMaxAmount = $selectedLoan ? (float) $selectedLoan->outstanding_balance : (float) $totalOutstandingBalance;
                                @endphp
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        Select Loan
                                    </label>
                                    <select name="loan_id" class="w-full px-4 py-3 rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                                        <option value="">All Loans (Nearest Due First)</option>
                                        @foreach($activeLoans as $loan)
                                            <option value="{{ $loan->id }}" data-balance="{{ number_format((float) $loan->outstanding_balance, 2, '.', '') }}" @if(old('loan_id') == $loan->id) selected @endif>
                                                {{ $loan->loan_number }} - ZMW {{ number_format($loan->outstanding_balance, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        Amount to Pay <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex items-center rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 focus-within:border-blue-500 dark:focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-500/20 transition">
                                        <span class="pl-4 pr-2 text-gray-600 dark:text-gray-300 font-bold text-base sm:text-lg whitespace-nowrap">ZMW</span>
                                        <input type="number" 
                                               name="amount" 
                                               id="amount"
                                               value="{{ old('amount') }}"
                                               min="0.01" 
                                               max="{{ number_format($partialMaxAmount, 2, '.', '') }}"
                                               step="0.01"
                                               class="w-full min-w-0 border-0 bg-transparent px-2 pr-4 py-3 text-gray-900 dark:text-white text-lg font-bold focus:outline-none focus:ring-0"
                                               placeholder="0.00">
                                    </div>
                                    <p id="amountMaxText" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Maximum: ZMW {{ number_format($partialMaxAmount, 2) }}
                                    </p>
                                    @error('amount')
                                        <p class="mt-1 text-red-500 text-sm">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <div class="repayment-type-option-indicator w-6 h-6 rounded-full border-2 border-gray-400 dark:border-gray-500 flex items-center justify-center transition-all">
                                <svg class="repayment-type-option-check w-4 h-4 opacity-0 transition-opacity" style="color: #F8F9FA !important;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </label>

            {{-- Overdue Payment Option --}}
            @if($hasOverdue && $totalOverdueAmount > 0)
                <label class="block cursor-pointer">
                    <input type="radio" name="repayment_type" value="overdue" 
                           class="peer sr-only" 
                           required
                           @if(old('repayment_type') == 'overdue') checked @endif>
                    <div class="repayment-type-option-card bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-xl p-5 shadow-md hover:shadow-lg transition-all peer-checked:border-red-500 peer-checked:bg-red-50 dark:peer-checked:bg-red-900/30 dark:peer-checked:border-red-400">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Pay Overdue Amount
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                                    Clear all overdue payments immediately
                                </p>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Amount to pay:</p>
                                    <p class="text-xl font-bold text-red-600 dark:text-red-400">ZMW {{ number_format($totalOverdueAmount, 2) }}</p>
                                </div>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                                <div class="repayment-type-option-indicator w-6 h-6 rounded-full border-2 border-gray-400 dark:border-gray-500 flex items-center justify-center transition-all">
                                    <svg class="repayment-type-option-check w-4 h-4 opacity-0 transition-opacity" style="color: #F8F9FA !important;" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </label>
            @endif

            @error('repayment_type')
                <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
            @enderror

            {{-- Action Buttons --}}
            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="{{ route('customer.dashboard') }}" 
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-6 py-3 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                <button type="submit" 
                        class="inline-flex items-center gap-2 rounded-xl bg-primary hover:opacity-90 text-white px-6 py-3 font-bold shadow-xl border border-primary transition transform hover:scale-[1.02] hover:shadow-2xl">
                    <span>Continue</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const repaymentTypeRadios = document.querySelectorAll('input[name="repayment_type"]');
            const partialPaymentFields = document.getElementById('partialPaymentFields');
            const amountInput = document.getElementById('amount');
            const loanSelect = document.querySelector('select[name="loan_id"]');
            const amountMaxText = document.getElementById('amountMaxText');
            const overallOutstanding = {{ (float) $totalOutstandingBalance }};

            function updateAmountLimit() {
                if (!amountInput || !loanSelect) {
                    return;
                }

                const selectedOption = loanSelect.options[loanSelect.selectedIndex];
                const selectedBalance = parseFloat(selectedOption?.dataset?.balance ?? '');
                const maxAllowed = Number.isFinite(selectedBalance) ? selectedBalance : overallOutstanding;

                amountInput.max = maxAllowed.toFixed(2);

                const currentValue = parseFloat(amountInput.value);
                if (Number.isFinite(currentValue) && currentValue > maxAllowed) {
                    amountInput.value = maxAllowed.toFixed(2);
                }

                if (amountMaxText) {
                    amountMaxText.textContent = `Maximum: ZMW ${maxAllowed.toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}`;
                }
            }

            repaymentTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'partial') {
                        partialPaymentFields.classList.remove('hidden');
                        amountInput.required = true;
                        updateAmountLimit();
                    } else {
                        partialPaymentFields.classList.add('hidden');
                        amountInput.required = false;
                        amountInput.value = '';
                    }
                });
            });

            loanSelect?.addEventListener('change', updateAmountLimit);
            updateAmountLimit();

            // Validate amount input
            if (amountInput) {
                amountInput.addEventListener('input', function(e) {
                    const maxAmount = parseFloat(e.target.max || '{{ number_format((float) $totalOutstandingBalance, 2, '.', '') }}');
                    const value = parseFloat(e.target.value);
                    if (value > maxAmount) {
                        e.target.value = maxAmount;
                    }
                });
            }
        });
    </script>
    @endpush
@endsection
