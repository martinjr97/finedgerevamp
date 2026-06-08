@extends('layouts.customer')

@section('title', 'Enter Loan Amount')

@section('content')
    <style>
        .loan-amount-input {
            padding-left: 4.75rem !important;
            padding-right: 1rem !important;
            line-height: 1.25rem;
        }

        .loan-amount-prefix {
            color: #6b7280 !important;
            pointer-events: none;
            z-index: 10;
        }

        .loan-amount-input::-webkit-outer-spin-button,
        .loan-amount-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .loan-amount-input[type='number'] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <div class="content-area space-y-6 max-w-2xl mx-auto">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-3xl font-bold mb-2 text-white">Enter Loan Amount</h1>
            <p class="text-blue-100">Specify the amount you want to borrow</p>
        </div>

        <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900 dark:to-emerald-900 border-2 border-green-300 dark:border-green-600 rounded-xl p-4 shadow-md">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-xs uppercase tracking-wider text-green-700 dark:text-green-300 font-semibold">Selected channel</p>
                    <p class="text-lg font-bold text-green-900 dark:text-white">{{ $channel->name }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('customer.loans.store-amount') }}" method="POST" class="space-y-6">
            @csrf

            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-6 shadow-lg">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    Loan Amount <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="loan-amount-prefix absolute left-4 top-1/2 -translate-y-1/2 font-bold text-lg">ZMW</span>
                    <input type="number"
                           name="amount"
                           id="amount"
                           value="{{ old('amount') }}"
                           min="1"
                           max="{{ isset($availableLoanAmount) ? min($maximumLoanTake, $availableLoanAmount) : $maximumLoanTake }}"
                           step="0.01"
                           required
                           class="loan-amount-input w-full py-4 rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-xl font-bold focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition"
                           placeholder="0.00">
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    @if(isset($availableLoanAmount) && $availableLoanAmount < $maximumLoanTake)
                        Available loan amount: <span class="font-bold text-blue-600 dark:text-blue-400">ZMW {{ number_format($availableLoanAmount, 2) }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-500 ml-2">(Maximum: ZMW {{ number_format($maximumLoanTake, 2) }})</span>
                    @else
                        Maximum loan amount: <span class="font-bold text-blue-600 dark:text-blue-400">ZMW {{ number_format($maximumLoanTake, 2) }}</span>
                    @endif
                </p>
                @error('amount')
                    <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="{{ route('customer.loans.select-channel') }}"
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-6 py-3 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Back
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-green-500 via-emerald-500 to-teal-600 hover:from-green-600 hover:via-emerald-600 hover:to-teal-700 text-white px-6 py-3 font-bold shadow-xl border-2 border-green-400 transition">
                    Continue
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.getElementById('amount')?.addEventListener('input', function (e) {
            const value = parseFloat(e.target.value);
            const maxAmount = {{ isset($availableLoanAmount) ? min($maximumLoanTake, $availableLoanAmount) : $maximumLoanTake }};
            if (value > maxAmount) {
                e.target.value = maxAmount;
            }
        });
    </script>
    @endpush
@endsection
