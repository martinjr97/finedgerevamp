@extends('layouts.customer')

@section('title', 'Loan Calculation')

@section('content')
    <div class="content-area space-y-6 max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-3xl font-bold mb-2 text-white">Loan Calculation</h1>
            <p class="text-blue-100">Review your loan details and repayment schedule</p>
        </div>

        {{-- Loan Summary Card --}}
        <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-6 shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Loan Summary</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Loan Amount</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">ZMW {{ number_format($loanAmount, 2) }}</p>
                </div>
                @if (! empty($loanPurpose))
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Loan Purpose</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $loanPurpose->name }}</p>
                    </div>
                @endif
                <div class="md:col-span-2">
                    @include('partials.customer.disbursement-destination-summary', [
                        'channel' => $channel,
                        'loanData' => $loanData,
                    ])
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Net Salary</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">ZMW {{ number_format($netSalary, 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Tenure Selection (if amount exceeds crossover) --}}
        @if($exceedsCrossover && !$showCalculation)
            <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border-2 border-amber-300 dark:border-amber-600 rounded-2xl p-6 shadow-lg">
                <div class="flex items-start gap-3 mb-4">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-amber-900 dark:text-amber-200 mb-1">Loan Amount Qualifies for Installments</h3>
                        <p class="text-sm text-amber-800 dark:text-amber-300">
                            Your loan amount (ZMW {{ number_format($loanAmount, 2) }}) exceeds 
                            {{ number_format($instalmentCrossOverPercentage ?? 0, 2) }}% of your net salary 
                            (ZMW {{ number_format($crossoverThreshold, 2) }}). 
                            Please select a repayment period.
                        </p>
                    </div>
                </div>

                <form action="{{ route('customer.loans.calculate.store') }}" method="POST" class="mt-6">
                    @csrf
                    <div>
                        <label for="tenure_months" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Select Repayment Period (Months)
                        </label>
                        <select name="tenure_months" id="tenure_months" required 
                                class="w-full rounded-xl bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            @for($i = 1; $i <= $maxTenureMonths; $i++)
                                <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'Month' : 'Months' }}</option>
                            @endfor
                        </select>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            Maximum tenure: {{ $maxTenureMonths }} months
                        </p>
                    </div>
                    <button type="submit" 
                            class="mt-4 w-full bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-[1.02]">
                        Calculate Loan
                    </button>
                </form>
            </div>
        @endif

        {{-- Loan Calculation Results --}}
        @if($showCalculation && $loanRate)
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-2 border-green-300 dark:border-green-600 rounded-2xl p-6 shadow-lg">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white">Loan Calculation Details</h2>
                
                <div class="space-y-4">
                    {{-- Repayment Period --}}
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-gray-600 dark:text-gray-400">Repayment Period</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedTenure }} {{ $selectedTenure === 1 ? 'Month' : 'Months' }}</span>
                    </div>

                    {{-- Loan Dates --}}
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-400">Loan Start Date</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $loanStartDate->format('M d, Y') }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-400">Loan End Date</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $loanEndDate->format('M d, Y') }}</span>
                        </div>
                    </div>

                    {{-- Loan Amount Breakdown --}}
                    <div class="mt-6 space-y-3">
                        <div class="flex justify-between items-center py-2">
                            <span class="text-gray-600 dark:text-gray-400">Principal Amount</span>
                            <span class="font-semibold text-gray-900 dark:text-white">ZMW {{ number_format($loanAmount, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-gray-600 dark:text-gray-400">
                                Processing Fee ({{ number_format($loanRate->processing_fee_percentage, 2) }}%)
                            </span>
                            <span class="font-semibold text-gray-900 dark:text-white">ZMW {{ number_format($processingFee, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-gray-600 dark:text-gray-400">
                                Interest 
                                @if($rateType->accrual_period === 'daily')
                                    ({{ number_format($loanRate->daily_rate * 100, 4) }}% daily × {{ $days }} days)
                                @else
                                    ({{ number_format($loanRate->weekly_rate * 100, 4) }}% weekly × {{ ceil($days / 7) }} weeks)
                                @endif
                            </span>
                            <span class="font-semibold text-gray-900 dark:text-white">ZMW {{ number_format($interest, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-3 pt-4 border-t-2 border-gray-300 dark:border-gray-600">
                            <span class="text-lg font-semibold text-gray-900 dark:text-white">Total Amount to Pay</span>
                            <span class="text-2xl font-bold text-green-600 dark:text-green-400">ZMW {{ number_format($totalAmount, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-gray-600 dark:text-gray-400">Monthly Payment</span>
                            <span class="text-xl font-bold text-blue-600 dark:text-blue-400">ZMW {{ number_format($monthlyPayment, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex gap-4">
                <a href="{{ route('customer.loans.enter-destination') }}"
                   class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-semibold py-3 px-6 rounded-xl transition text-center">
                    Back to Edit
                </a>
                <form action="{{ route('customer.loans.store') }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-[1.02]">
                        Confirm & Apply
                    </button>
                </form>
            </div>
        @endif

        {{-- Info Card (if amount doesn't exceed crossover) --}}
        @if(!$exceedsCrossover && !$showCalculation)
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-2 border-blue-300 dark:border-blue-600 rounded-2xl p-6 shadow-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-200 mb-1">Loan Defaulted to 1 Month</h3>
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            Your loan amount is within the acceptable threshold. The loan has been set to a 1-month repayment period.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
