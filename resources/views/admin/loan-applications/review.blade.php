@extends('layouts.admin')

@section('title', 'Review Loan | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Review Loan Application',
            'description' => 'Confirm the loan details before submitting the application',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-applications.loan-details', [$loanProduct, $customer]),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Step Indicator --}}
        <div class="flex items-center justify-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Product Selected</span>
                </div>
                <div class="h-1 w-16 bg-emerald-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Customer Selected</span>
                </div>
                <div class="h-1 w-16 bg-emerald-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white font-semibold">✓</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Loan Details</span>
                </div>
                <div class="h-1 w-16 bg-cyan-500"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-white font-semibold">4</div>
                    <span class="ml-2 text-sm font-medium text-white">Review & Confirm</span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Customer & Company --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-lg font-semibold text-white mb-2">Customer & Company</h2>
                <div class="space-y-3 text-sm text-slate-200">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Customer</p>
                        <p class="text-base font-semibold text-white">
                            {{ $customer->full_name }} (ID: {{ $customer->id }})
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Company</p>
                        <p class="text-base font-semibold text-white">
                            {{ $company->name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Product</p>
                        <p class="text-base font-semibold text-white">
                            {{ $loanProduct->name }} ({{ $loanProduct->code }})
                        </p>
                    </div>
                    @if($rateType)
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Rate Type</p>
                            <p class="text-base font-semibold text-white">
                                {{ $rateType->name }} ({{ $rateType->code }}) • {{ ucfirst($rateType->accrual_period) }} accrual
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Config Summary --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-lg font-semibold text-white mb-2">Company Configuration</h2>
                <div class="space-y-3 text-sm text-slate-200">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Maximum Loan Tenure (Months)</p>
                        <p class="text-base font-semibold text-white">
                            {{ $company->maximum_loan_tenure_months ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Maximum Debit Ratio (%)</p>
                        <p class="text-base font-semibold text-white">
                            {{ $company->maximum_debit_ratio !== null ? number_format($company->maximum_debit_ratio, 2) : 'Not set' }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Monthly Cut-off Day</p>
                            <p class="text-base font-semibold text-white">
                                {{ $company->monthly_cut_off_day ?? 'Not set' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Pay Day</p>
                            <p class="text-base font-semibold text-white">
                                {{ $company->pay_day ?? 'Not set' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loan Summary --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <h2 class="text-lg font-semibold text-white">Loan Summary</h2>
            <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-4 text-sm text-slate-200">
                @include('partials.loan-purpose-summary', ['loanPurpose' => $loanPurpose ?? null])
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Loan Amount</p>
                    <p class="text-base font-semibold text-white">
                        ZMW {{ number_format($loanData['loan_amount'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Processing Fee</p>
                    <p class="text-base font-semibold text-white">
                        ZMW {{ number_format($loanData['processing_fee'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Interest</p>
                    <p class="text-base font-semibold text-white">
                        ZMW {{ number_format($loanData['interest'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total Amount</p>
                    <p class="text-base font-semibold text-emerald-400">
                        ZMW {{ number_format($loanData['total_amount'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Tenure</p>
                    <p class="text-base font-semibold text-white">
                        {{ $loanData['tenure_months'] }} months
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Start Date</p>
                    <p class="text-base font-semibold text-white">
                        {{ \Carbon\Carbon::parse($loanData['loan_start_date'])->format('d M Y') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">End Date</p>
                    <p class="text-base font-semibold text-white">
                        {{ \Carbon\Carbon::parse($loanData['loan_end_date'])->format('d M Y') }}
                    </p>
                </div>
                <div class="md:col-span-2">
                    @include('partials.admin.disbursement-destination-summary', [
                        'channel' => $channel,
                        'loanData' => $loanData,
                    ])
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.loan-applications.loan-details', [$loanProduct, $customer]) }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                    Back to Loan Details
                </a>
                <form method="POST" action="{{ route('admin.loan-applications.store-mou', [$loanProduct, $customer]) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-emerald-700 transition">
                        Confirm & Create Loan
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

