@extends('layouts.customer')

@section('title', 'Account Statement')

@section('content')
    <div class="content-area space-y-6 max-w-6xl mx-auto">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-3xl font-bold mb-2 text-white">Account Statement</h1>
            <p class="text-blue-100">View your loan history and transaction details</p>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 border-2 border-blue-200 dark:border-blue-700 rounded-xl p-4 shadow-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Loans</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $summary['total_loans'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    {{ $summary['active_loans'] }} Active, {{ $summary['completed_loans'] }} Completed
                </p>
            </div>
            
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900 dark:to-emerald-900 border-2 border-green-200 dark:border-green-700 rounded-xl p-4 shadow-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Borrowed</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">ZMW {{ number_format($summary['total_borrowed'], 2) }}</p>
            </div>
            
            <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900 dark:to-orange-900 border-2 border-amber-200 dark:border-amber-700 rounded-xl p-4 shadow-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total Interest</p>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">ZMW {{ number_format($summary['total_interest'], 2) }}</p>
            </div>
            
            <div class="bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 border-2 border-purple-200 dark:border-purple-700 rounded-xl p-4 shadow-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Booked Outstanding</p>
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">ZMW {{ number_format($summary['total_outstanding'], 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    Paid: ZMW {{ number_format($summary['total_paid'], 2) }}
                </p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-6 shadow-lg">
            <form method="GET" action="{{ route('customer.statement') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Filter by Loan
                    </label>
                    <select name="loan_id" class="w-full px-4 py-2 rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                        <option value="">All Loans</option>
                        @foreach($loans as $loan)
                            <option value="{{ $loan->id }}" @if(request('loan_id') == $loan->id) selected @endif>
                                {{ $loan->loan_number }} - {{ $loan->loanProduct->name ?? 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Start Date
                    </label>
                    <input type="date" 
                           name="start_date" 
                           value="{{ request('start_date', $startDate) }}"
                           class="w-full px-4 py-2 rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        End Date
                    </label>
                    <input type="date" 
                           name="end_date" 
                           value="{{ request('end_date', $endDate) }}"
                           class="w-full px-4 py-2 rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition">
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" 
                            class="flex-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 hover:from-blue-600 hover:via-indigo-600 hover:to-purple-700 text-white rounded-xl px-4 py-2 font-bold shadow-xl border-2 border-blue-400 transition transform hover:scale-[1.02] hover:shadow-2xl">
                        Filter
                    </button>
                    @if(request('loan_id') || request('start_date') || request('end_date'))
                        <a href="{{ route('customer.statement') }}" 
                           class="px-4 py-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Transaction History --}}
        @if($transactions->isEmpty())
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-12 shadow-lg text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-lg text-gray-600 dark:text-gray-400 font-medium">No transactions found</p>
                <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">Try adjusting your filters or check back later</p>
            </div>
        @else
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-6 shadow-lg">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Transaction History</h2>
                
                <div class="space-y-4">
                    @foreach($transactions as $index => $transaction)
                        @php
                            $loan = $transaction['loan'];
                            $isFirst = $index === 0;
                            $isLast = $index === count($transactions) - 1;
                            
                            // Determine transaction type styling
                            $typeColors = [
                                'loan_created' => 'from-green-500 to-emerald-500',
                                'accrual' => 'from-blue-500 to-indigo-500',
                                'payment' => 'from-purple-500 to-pink-500',
                                'refund' => 'from-rose-500 to-red-500',
                                'loan_settled' => 'from-gray-500 to-slate-500',
                            ];
                            $colorClass = $typeColors[$transaction['type']] ?? 'from-gray-500 to-slate-500';
                            
                            // Transaction icons
                            $typeIcons = [
                                'loan_created' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                'accrual' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                                'payment' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                                'refund' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
                                'loan_settled' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                            ];
                            $iconPath = $typeIcons[$transaction['type']] ?? '';
                        @endphp
                        
                        <div class="flex gap-4">
                            {{-- Timeline Line --}}
                            <div class="flex flex-col items-center">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br {{ $colorClass }} p-2 flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
                                    </svg>
                                </div>
                                @if(!$isLast)
                                    <div class="w-0.5 h-full bg-gray-300 dark:bg-gray-600 my-2"></div>
                                @endif
                            </div>
                            
                            {{-- Transaction Details --}}
                            <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 p-4 shadow-md hover:shadow-lg transition">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                                {{ $transaction['description'] }}
                                            </h3>
                                            @php
                                                $typeBadgeClasses = [
                                                    'payment' => 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300',
                                                    'refund' => 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300',
                                                    'accrual' => 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300',
                                                    'loan_created' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                                                    'loan_settled' => 'bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300',
                                                ];
                                                $badgeClass = $typeBadgeClasses[$transaction['type']] ?? 'bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300';
                                            @endphp
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $badgeClass }} capitalize">
                                                {{ str_replace('_', ' ', $transaction['type']) }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $transaction['date']->format('l, F j, Y g:i A') }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                                            Loan: <span class="font-semibold">{{ $loan->loan_number }}</span> | 
                                            Product: <span class="font-semibold">{{ $loan->loanProduct->name ?? 'N/A' }}</span>
                                        </p>
                                        @if(isset($transaction['rate_used']))
                                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                                Rate: {{ number_format($transaction['rate_used'] * 100, 4) }}%
                                            </p>
                                        @endif
                                    </div>
                                    
                                    <div class="text-right space-y-1">
                                        @if($transaction['principal'] > 0 || $transaction['processing_fee'] > 0)
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Principal: <span class="font-bold text-gray-900 dark:text-white">ZMW {{ number_format($transaction['principal'], 2) }}</span>
                                            </p>
                                            @if($transaction['processing_fee'] > 0)
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    Fee: <span class="font-bold text-gray-900 dark:text-white">ZMW {{ number_format($transaction['processing_fee'], 2) }}</span>
                                                </p>
                                            @endif
                                        @endif
                                        @if($transaction['interest'] > 0)
                                            <p class="text-sm text-amber-600 dark:text-amber-400">
                                                Interest: <span class="font-bold">ZMW {{ number_format($transaction['interest'], 2) }}</span>
                                            </p>
                                        @endif
                                        @if($transaction['payment'] != 0)
                                            @php
                                                $paymentAmount = (float) $transaction['payment'];
                                                $isRefundTxn = $transaction['type'] === 'refund' || $paymentAmount < 0;
                                                $paymentLabel = $isRefundTxn ? 'Refund' : 'Payment';
                                                $paymentColor = $isRefundTxn ? 'text-rose-600 dark:text-rose-400' : 'text-green-600 dark:text-green-400';
                                                $displayAmount = abs($paymentAmount);
                                            @endphp
                                            <p class="text-sm {{ $paymentColor }}">
                                                {{ $paymentLabel }}:
                                                <span class="font-bold">
                                                    @if ($isRefundTxn)
                                                        +ZMW {{ number_format($displayAmount, 2) }}
                                                    @else
                                                        -ZMW {{ number_format($displayAmount, 2) }}
                                                    @endif
                                                </span>
                                            </p>
                                            @if(isset($transaction['loan_repayment']))
                                                @php $lr = $transaction['loan_repayment']; @endphp
                                                <div class="text-xs text-gray-500 dark:text-gray-500 mt-1 space-y-0.5">
                                                    @if(abs((float) $lr->principal_amount) > 0)
                                                        <p>Principal: {{ $isRefundTxn ? '+' : '-' }}ZMW {{ number_format(abs((float) $lr->principal_amount), 2) }}</p>
                                                    @endif
                                                    @if(abs((float) $lr->interest_amount) > 0)
                                                        <p>Interest: {{ $isRefundTxn ? '+' : '-' }}ZMW {{ number_format(abs((float) $lr->interest_amount), 2) }}</p>
                                                    @endif
                                                    @if(abs((float) $lr->processing_fee_amount) > 0)
                                                        <p>Fee: {{ $isRefundTxn ? '+' : '-' }}ZMW {{ number_format(abs((float) $lr->processing_fee_amount), 2) }}</p>
                                                    @endif
                                                </div>
                                                @if(isset($transaction['repayment']) && $transaction['repayment']->external_reference)
                                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                                        Ref: {{ $transaction['repayment']->external_reference }}
                                                    </p>
                                                @endif
                                            @endif
                                        @endif
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
                                            Outstanding: <span class="font-bold text-blue-600 dark:text-blue-400">ZMW {{ number_format($transaction['outstanding_balance'], 2) }}</span>
                                        </p>
                                        @if(isset($transaction['net_paid']))
                                            <p class="text-xs text-gray-500 dark:text-gray-500">
                                                Net paid after transaction: ZMW {{ number_format($transaction['net_paid'], 2) }}
                                                @if(($transaction['suspense_amount'] ?? 0) > 0)
                                                    · Suspense ZMW {{ number_format($transaction['suspense_amount'], 2) }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Active Loans Summary --}}
        @if($loans->whereIn('status', ['approved', 'active'])->count() > 0)
            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-6 shadow-lg">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Active Loans</h2>
                <div class="space-y-3">
                    @foreach($loans->whereIn('status', ['approved', 'active']) as $loan)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 p-4 shadow-md">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white">{{ $loan->loan_number }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $loan->loanProduct->name ?? 'N/A' }} | 
                                        Started: {{ $loan->loan_start_date->format('M d, Y') }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Booked Outstanding Balance</p>
                                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">ZMW {{ number_format($loan->outstanding_balance, 2) }}</p>
                                    @if($loan->amount_paid > 0)
                                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                            Paid: ZMW {{ number_format($loan->amount_paid, 2) }}
                                        </p>
                                    @endif
                                    @if($loan->first_payment_date)
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                            Next Payment: {{ $loan->first_payment_date->format('M d, Y') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
