@php
    $hour = (int) date('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $loanProduct = $customer->loanProduct;
    $maximumLoanTake = $customer->maximum_loan_take ?? 0;
    $hasActiveLoans = $activeLoans->count() > 0;
    $isGroupLoanCustomer = $isGroupLoanCustomer ?? ((string) ($loanProduct?->category ?? '') === 'group_loans');
    $relationshipManager = $customer->customerGroup?->relationshipManager;
@endphp

@extends('layouts.customer')

@section('title', 'Dashboard')

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-8" x-data="{ showPendingLoanModal: false }">
        {{-- One wrapper card: border, shadow, all content inside --}}
        <div id="dashboard-stats" class="bg-white dark:bg-gray-800/50 rounded-2xl border border-slate-200 dark:border-slate-600 shadow-xl overflow-hidden">
            <div class="p-4 sm:p-6 space-y-4">
        {{-- Section: Welcome + Primary CTA --}}
        <section class="space-y-3">
            <div class="bg-gradient-to-r from-purple-600 via-purple-700 to-indigo-700 rounded-2xl p-5 sm:p-6 shadow-lg">
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $greeting }}, {{ $customer->first_name }}!</h1>
                <p class="text-purple-100 text-sm sm:text-base mt-1">Welcome to your loan management dashboard</p>
                @if($loanProduct && $canStartLoanFlow)
                @if($loanProduct->category === 'collateral')
                    <a href="{{ route('customer.collateral-loans.loan-details') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3.5 shadow-md hover:shadow-lg transition border border-green-700/30">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ $hasActiveLoans ? 'Apply for Another Loan' : 'Get a Loan' }}</span>
                    </a>
                @else
                    <a href="{{ route('customer.loans.select-channel') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3.5 shadow-md hover:shadow-lg transition border border-green-700/30">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ $hasActiveLoans ? 'Apply for Another Loan' : 'Get a Loan' }}</span>
                    </a>
                @endif
            @elseif($hasActiveLoans)
                <a href="{{ route('customer.repayments.select-type') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3.5 shadow-md hover:shadow-lg transition border border-green-700/30">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Make a Loan Repayment</span>
                </a>
            @elseif($isGroupLoanCustomer)
                <p class="mt-3 inline-flex rounded-xl border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-900">
                    Group loan requests are handled by your Relationship Manager.
                </p>
            @endif
            </div>


        </section>

        @if($pendingReviewLoan)
            <section class="pt-0">
                <button
                    type="button"
                    @click="showPendingLoanModal = true"
                    class="w-full rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-left shadow-sm transition hover:bg-amber-100"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Loan Application Update</p>
                            <p class="mt-1 text-sm text-slate-700">
                                Your loan application
                                <span class="font-semibold text-slate-900">{{ $pendingReviewLoan->loan_number ?? ('#'.$pendingReviewLoan->id) }}</span>
                                is under review. Tap to view summary.
                            </p>
                        </div>
                        <span class="inline-flex whitespace-nowrap rounded-full border border-amber-400 bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                            Pending Review
                        </span>
                    </div>
                </button>
            </section>
        @endif

        {{-- Section: Loan info (qualification or balance) --}}
        <section class="pt-0">
        @if($hasActiveLoans)
            {{-- Active Loans Card - light background for readability --}}
            <div class="bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 rounded-2xl p-6 shadow-sm">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Current Loan Balance</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Booked outstanding across active loans (amount you owe today)</p>
                    </div>
                    <div class="rounded-full bg-blue-600 p-3 shadow-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-600 shadow-sm">
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-1">Booked Outstanding Balance</p>
                        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300">ZMW {{ number_format($totalOutstandingBalance, 2) }}</p>
                    </div>
                    @if(isset($projectedRepaymentTotal) && abs($projectedRepaymentTotal - $totalOutstandingBalance) > 0.01)
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-600 shadow-sm">
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-1">Projected Full Repayment</p>
                            <p class="text-xl font-bold text-slate-700 dark:text-slate-200">ZMW {{ number_format($projectedRepaymentTotal, 2) }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Expected total if all installments are paid per schedule (includes future interest where applicable).</p>
                        </div>
                    @endif
                    @if(isset($primaryActiveLoan) && $primaryActiveLoan)
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-600 shadow-sm text-sm space-y-1">
                            <p><span class="text-slate-500">Processing fee:</span> <span class="font-medium text-slate-900 dark:text-white">ZMW {{ number_format($primaryActiveLoan->processing_fee, 2) }}</span></p>
                            <p><span class="text-slate-500">Earned interest:</span> <span class="font-medium">ZMW {{ number_format($primaryActiveLoan->getEarnedInterest(), 2) }}</span></p>
                            @if($primaryActiveLoan->quoted_term_rate)
                                <p><span class="text-slate-500">Term rate:</span> <span class="font-medium">{{ number_format($primaryActiveLoan->quoted_term_rate, 2) }}%</span></p>
                            @endif
                            <p><span class="text-slate-500">Interest behavior:</span> <span class="font-medium">{{ $primaryActiveLoan->getInterestBehaviorLabel() }}</span></p>
                        </div>
                    @endif
                    @if($nextPaymentDate)
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                            <p class="text-xs uppercase tracking-wider text-amber-700 dark:text-amber-300 mb-1 font-semibold">Next Payment Date</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $nextPaymentDate->format('d M Y') }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ $nextPaymentDate->diffForHumans() }}</p>
                        </div>
                    @endif
                    @if($availableLoanAmount > 0 && $canStartLoanFlow)
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl p-4">
                            <p class="text-xs uppercase tracking-wider text-green-700 dark:text-green-300 mb-1 font-semibold">Available for New Loan</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">ZMW {{ number_format($availableLoanAmount, 2) }}</p>
                        </div>
                    @elseif(!empty($loanEligibilityBlockingMessage) && !($isGroupLoanCustomer ?? false))
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                            <p class="text-xs uppercase tracking-wider text-amber-700 dark:text-amber-300 mb-1 font-semibold">New loan unavailable</p>
                            <p class="text-sm text-slate-700 dark:text-slate-200">{{ $loanEligibilityBlockingMessage }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            {{-- Loan Qualification Card - light background so all text is readable --}}
            <div class="bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 rounded-2xl p-6 shadow-sm">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Loan Qualification</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Based on your profile and income</p>
                    </div>
                    <div class="rounded-full bg-blue-600 p-3 shadow-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-600 shadow-sm">
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-1">Maximum Loan Amount</p>
                        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300">ZMW {{ number_format($maximumLoanTake, 2) }}</p>
                    </div>
                    @if(!empty($loanEligibilityBlockingMessage) && !($isGroupLoanCustomer ?? false))
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                            <p class="text-sm text-amber-800 dark:text-amber-200">{{ $loanEligibilityBlockingMessage }}</p>
                        </div>
                    @endif
                    @if($loanProduct)
                        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-600 shadow-sm">
                            <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1 font-semibold">Product Type</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $loanProduct->name }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ $loanProduct->description ?? 'No description available' }}</p>
                        </div>
                    @else
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                            <p class="text-sm text-amber-800 dark:text-amber-200 font-medium">No loan product assigned yet. Please contact support.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        
        </section>

        {{-- Section: Status + Account in a neat grid --}}
        <section class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 pt-0">
            {{-- Status cards row --}}
            <div class="grid grid-cols-2 gap-3 md:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow border border-slate-200 dark:border-slate-600">
                    <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 font-semibold">Status</p>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-700">
                        {{ $customer->status ?? 'Pending' }}
                    </span>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow border border-slate-200 dark:border-slate-600">
                    <p class="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 font-semibold">KYC Status</p>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200 border border-green-200 dark:border-green-700">
                        {{ $customer->kyc_status ?? 'Unverified' }}
                    </span>
                </div>
            </div>

            {{-- Account Information --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow border border-slate-200 dark:border-slate-600 md:col-span-2 lg:col-span-1">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Account Information
                </h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500 dark:text-slate-400">Email</dt>
                        <dd class="font-medium text-slate-900 dark:text-white truncate" title="{{ $customer->email }}">{{ $customer->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 border-t border-slate-100 dark:border-slate-700 pt-2.5">
                        <dt class="text-slate-500 dark:text-slate-400">Phone</dt>
                        <dd class="font-medium text-slate-900 dark:text-white">{{ $customer->phone ?? '—' }}</dd>
                    </div>
                    @if($customer->company)
                        <div class="flex justify-between gap-2 border-t border-slate-100 dark:border-slate-700 pt-2.5">
                            <dt class="text-slate-500 dark:text-slate-400">Company</dt>
                            <dd class="font-medium text-slate-900 dark:text-white">{{ $customer->company->name }}</dd>
                        </div>
                    @endif
                    @if($isGroupLoanCustomer)
                        <div class="flex justify-between gap-2 border-t border-slate-100 dark:border-slate-700 pt-2.5">
                            <dt class="text-slate-500 dark:text-slate-400">Relationship Manager</dt>
                            <dd class="font-medium text-slate-900 dark:text-white text-right">
                                {{ $relationshipManager?->full_name ?? 'Not assigned' }}
                                @if($relationshipManager?->phone)
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $relationshipManager->phone }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Need Help --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow border border-slate-200 dark:border-slate-600 md:col-span-2 lg:col-span-1 flex flex-col">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636A9 9 0 105.636 18.364 9 9 0 0018.364 5.636zM12 8v4m0 4h.01"/></svg>
                    Need Help?
                </h3>
                <p class="text-sm text-slate-600 dark:text-slate-300 flex-1">
                    Questions about loans, repayments, or your account? Submit a support ticket and we’ll get back to you.
                </p>
                <a href="{{ route('customer.support') }}" class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-700 dark:hover:bg-slate-600 text-white px-4 py-2.5 text-sm font-semibold transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Submit Support Ticket
                </a>
            </div>
        </section>

        @if($pendingReviewLoan)
            <div
                x-show="showPendingLoanModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pending-loan-modal-title"
                @keydown.escape.window="showPendingLoanModal = false"
            >
                <div class="absolute inset-0 bg-slate-900/50" @click="showPendingLoanModal = false"></div>

                <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <h2 id="pending-loan-modal-title" class="text-lg font-bold text-slate-900">Loan Under Review</h2>
                        <button
                            type="button"
                            @click="showPendingLoanModal = false"
                            class="rounded-lg border border-slate-300 px-2 py-1 text-slate-600 transition hover:bg-slate-50"
                            aria-label="Close loan summary"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-slate-600">
                            Your application has been received and is currently with our team for review.
                        </p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $pendingReviewLoan->loan_number ?? ('#'.$pendingReviewLoan->id) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                <p class="mt-1 font-semibold text-amber-700">{{ ucfirst(str_replace('_', ' ', $pendingReviewLoan->status)) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested Amount</p>
                                <p class="mt-1 font-semibold text-slate-900">ZMW {{ number_format((float) $pendingReviewLoan->principal_amount, 2) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Repayment Period</p>
                                <p class="mt-1 font-semibold text-slate-900">
                                    {{ $pendingReviewLoan->tenure_months ? $pendingReviewLoan->tenure_months.' month(s)' : 'Not set' }}
                                </p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Product</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $pendingReviewLoan->loanProduct->name ?? ($loanProduct->name ?? 'N/A') }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Disbursement destination</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $pendingReviewLoan->disbursementDestinationSummary() ?: ($pendingReviewLoan->channel->name ?? 'Not selected yet') }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted On</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $pendingReviewLoan->created_at?->format('d M Y, h:i A') ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 px-5 py-4">
                        <button
                            type="button"
                            @click="showPendingLoanModal = false"
                            class="w-full rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif
            </div>
        </div>
    </div>
@endsection
