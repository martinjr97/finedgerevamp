@extends('layouts.admin')

@section('title', 'View Loan | '.config('app.system_name'))

@section('content')
    @php
        $paymentDetailsEditable = $loan->status === 'pending_approval'
            || ($loan->status === 'approved' && $loan->disbursement_status === 'pending');
        $paymentDetailsStageLabel = $loan->status === 'pending_approval'
            ? 'before approval'
            : 'before disbursement';
    @endphp

    <div class="space-y-8">
        @include('partials.admin.shared-payment-details-alert', [
            'loan' => $loan,
            'sharedPaymentDetails' => $sharedPaymentDetails ?? ['has_matches' => false, 'matches' => []],
        ])

        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Loan Management</p>
                <h1 class="text-3xl font-bold">{{ $loan->loan_number }}</h1>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                @if ($loan->status === 'pending_approval')
                    @can('loans.approve')
                    <button type="button"
                            onclick="showApproveModal()"
                            class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200/40 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-700 hover:to-teal-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Approve Loan
                    </button>
                    @endcan
                    @can('loans.reject')
                    <button type="button" onclick="showRejectModal({{ $loan->id }})" class="btn-reject-critical">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Reject Loan
                    </button>
                    @endcan
                @endif

                {{-- Manual disbursement button --}}
                @if(
                    isset($disbursementType) && $disbursementType === 'manual' &&
                    $loan->status === 'approved' &&
                    $loan->disbursement_status === 'pending'
                )
                    @can('loans.disburse')
                    <button type="button"
                            onclick="openDisbursementModal()"
                            class="inline-flex items-center gap-2 rounded-2xl border border-cyan-200/40 bg-gradient-to-r from-slate-800 to-cyan-700 px-4 py-3 font-semibold text-white shadow-lg shadow-slate-900/40 hover:from-slate-900 hover:to-cyan-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3m6 0a3 3 0 00-3-3m0 0V5m0 6v6m9-6a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Record Disbursement
                    </button>
                    @endcan
                @endif

                @if($paymentDetailsEditable)
                    @can('loans.update-payment-details')
                    <button type="button"
                            id="changePaymentDetailsButton"
                            onclick="openPaymentDetailsModal()"
                            class="inline-flex items-center gap-2 rounded-2xl border border-amber-200/40 bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-3 font-semibold text-white shadow-lg shadow-amber-500/30 hover:from-amber-600 hover:to-orange-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h10m-10 5h16"/>
                        </svg>
                        Change Payment Details
                    </button>
                    @endcan
                @endif

                @if($loan->amount_paid > 0 && $loan->loanRepayments->isEmpty())
                    <form method="POST" action="{{ route('admin.loans.backfill-repayment', $loan) }}" class="inline-block" onsubmit="return confirm('This will create repayment records for this loan based on existing payment data. Continue?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-purple-600 px-4 py-3 font-semibold text-white shadow-lg shadow-purple-500/30 hover:bg-purple-700 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Backfill Repayment Records
                        </button>
                    </form>
                @endif
                @php
                    $loanCanAcceptRepayment = $loan->isActive()
                        && (float) $loan->outstanding_balance > 0;
                @endphp
                @can('repayments.create')
                    @if($loanCanAcceptRepayment && $loan->customer_id)
                    <a href="{{ route('admin.customers.repayments.create', ['customer' => $loan->customer_id, 'loan_id' => $loan->id]) }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-emerald-300/40 bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-teal-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Record Repayment
                    </a>
                    @endif
                @endcan
                @can('loan.extend')
                    @php
                        $extensionLimitReached = $loan->loanExtensions->count() >= 3;
                        $loanCanBeExtended = $loan->status === 'active' && (float) $loan->outstanding_balance > 0 && !in_array($loan->status, ['defaulted', 'settled', 'completed', 'cancelled'], true);
                        $extensionButtonDisabled = $extensionLimitReached || !$loanCanBeExtended;
                    @endphp
                    <button
                        type="button"
                        onclick="openExtensionModal()"
                        @disabled($extensionButtonDisabled)
                        class="inline-flex items-center gap-2 rounded-2xl border border-amber-300/40 bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-3 font-semibold text-white shadow-lg shadow-amber-500/30 hover:from-amber-600 hover:to-orange-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Extend Loan
                    </button>
                @endcan
                @if ($canRefundRepayments && $refundableLoanRepayments->isNotEmpty())
                    @php
                        $primaryRefundable = $refundableLoanRepayments->first();
                        $primaryRepayment = $primaryRefundable->repayment;
                    @endphp
                    <button
                        type="button"
                        onclick="openRefundModal({{ $primaryRefundable->id }}, '{{ $primaryRepayment->repayment_number }}', {{ number_format($primaryRefundable->refundableAmountRemaining(), 2, '.', '') }})"
                        class="inline-flex items-center gap-2 rounded-2xl border border-rose-300/40 bg-gradient-to-r from-rose-600 to-red-600 px-4 py-3 font-semibold text-white shadow-lg shadow-rose-500/30 hover:from-rose-700 hover:to-red-700 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Issue Refund
                    </button>
                @endif
                <a href="{{ route('admin.loans.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                    Back to List
                </a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Loan Details --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Loan Details</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Loan Number:</span>
                        <span class="font-medium text-white">{{ $loan->loan_number }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        @php
                            $statusColors = [
                                'pending_approval' => 'bg-amber-500/20 text-amber-300',
                                'approved' => 'bg-blue-500/20 text-blue-300',
                                'active' => 'bg-emerald-500/20 text-emerald-300',
                                'completed' => 'bg-green-500/20 text-green-300',
                                'settled' => 'bg-teal-500/20 text-teal-300',
                                'defaulted' => 'bg-rose-500/20 text-rose-300',
                                'cancelled' => 'bg-slate-500/20 text-slate-300',
                            ];
                            $statusColor = $statusColors[$loan->status] ?? 'bg-slate-500/20 text-slate-300';
                        @endphp
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $statusColor }}">
                            {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Product:</span>
                        <span class="font-medium text-white">{{ $loan->loanProduct->name ?? '—' }}</span>
                    </div>
                    @if ($loan->customerGroup)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Customer Group:</span>
                            <a href="{{ route('admin.customer-groups.show', $loan->customerGroup) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                {{ $loan->customerGroup->name }}
                            </a>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Accrual Type:</span>
                        <span class="font-medium text-white">{{ ucfirst(str_replace('_', ' ', $loan->accrual_type)) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Tenure:</span>
                        <span class="font-medium text-white">{{ $loan->tenure_months }} {{ $loan->tenure_months === 1 ? 'Month' : 'Months' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Start Date:</span>
                        <span class="font-medium text-white">{{ $loan->loan_start_date->format('d M Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">End Date:</span>
                        <span class="font-medium text-white">{{ $loan->loan_end_date->format('d M Y') }}</span>
                    </div>
                    @if ($loan->first_payment_date)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">First Payment Date:</span>
                            <span class="font-medium text-white">{{ $loan->first_payment_date->format('d M Y') }}</span>
                        </div>
                    @endif
                    @if ($loan->last_payment_date)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Last Payment Date:</span>
                            <span class="font-medium text-white">{{ $loan->last_payment_date->format('d M Y') }}</span>
                        </div>
                    @endif
                    @if ($loan->loan_settled_date)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Settled Date:</span>
                            <span class="font-medium text-white">{{ $loan->loan_settled_date->format('d M Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @include('admin.loans.partials.financial-summary')

            @if ($loan->accrual_period || $loan->last_accrual_date)
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Accrual</h2>
                    <div class="space-y-3 text-sm">
                        @if ($loan->accrual_period)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Accrual period</span>
                                <span class="font-medium text-white">{{ ucfirst($loan->accrual_period) }}</span>
                            </div>
                        @endif
                        @if ($loan->last_accrual_date)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Last accrual date</span>
                                <span class="font-medium text-white">{{ $loan->last_accrual_date->format('d M Y') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Customer Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Customer Information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Name:</span>
                        @if($loan->customer)
                            <a href="{{ route('admin.customers.show', $loan->customer) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                {{ $loan->customer->full_name }}
                            </a>
                        @else
                            <span class="font-medium text-white">—</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Email:</span>
                        <span class="font-medium text-white">{{ $loan->customer->email ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Phone:</span>
                        <span class="font-medium text-white">{{ $loan->customer->phone ?? '—' }}</span>
                    </div>
                    <div class="border-t border-white/10 pt-3 mt-1">
                        @include('partials.admin.disbursement-destination-summary', ['loan' => $loan])
                    </div>
                    @if($loan->disbursed_via_type)
                        <div class="flex items-center justify-between border-t border-white/10 pt-3">
                            <span class="text-slate-400">Disbursed From (treasury):</span>
                            <span class="font-medium text-white">{{ ucfirst($loan->disbursed_via_type) }} #{{ $loan->disbursed_via_id }}</span>
                        </div>
                    @endif
                    @if ($loan->disbursement_reference)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Disbursement Reference:</span>
                            <span class="font-medium text-white">{{ $loan->disbursement_reference }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Disbursement Status:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $loan->disbursement_status === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : ($loan->disbursement_status === 'processing' ? 'bg-blue-500/20 text-blue-300' : ($loan->disbursement_status === 'failed' ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-300')) }}">
                            {{ ucfirst($loan->disbursement_status) }}
                        </span>
                    </div>
                    @if ($loan->disbursed_at)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Disbursed At:</span>
                            <span class="font-medium text-white">{{ $loan->disbursed_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    @if ($loan->disbursement_notes)
                        <div>
                            <span class="text-slate-400">Disbursement Description:</span>
                            <p class="font-medium text-white mt-1">{{ $loan->disbursement_notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Approval Information --}}
            @if ($loan->approved_by || $loan->approval_notes)
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-xl font-semibold text-white">Approval Information</h2>
                    <div class="space-y-3 text-sm">
                        @if ($loan->approver)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Approved By:</span>
                                <span class="font-medium text-white">{{ $loan->approver->full_name ?? '—' }}</span>
                            </div>
                        @endif
                        @if ($loan->approved_at)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Approved At:</span>
                                <span class="font-medium text-white">{{ $loan->approved_at->format('d M Y H:i') }}</span>
                            </div>
                        @endif
                        @if ($loan->approval_notes)
                            <div>
                                <span class="text-slate-400">Notes:</span>
                                <p class="font-medium text-white mt-1">{{ $loan->approval_notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if ($paymentDetailChangeTrail->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-white">Payment Details Change Trail</h2>
                    <span class="text-xs text-slate-400">Reason and notification are captured for every change</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="bg-slate-100 text-center text-sm font-semibold uppercase tracking-[0.2em] border-b border-slate-300">
                                <th class="px-4 py-3 text-left text-slate-800">Changed At</th>
                                <th class="px-4 py-3 text-left text-slate-800">Stage</th>
                                <th class="px-4 py-3 text-left text-slate-800">Admin</th>
                                <th class="px-4 py-3 text-left text-slate-800">Previous Details</th>
                                <th class="px-4 py-3 text-left text-slate-800">New Details</th>
                                <th class="px-4 py-3 text-left text-slate-800">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentDetailChangeTrail as $change)
                                <tr class="border-t border-white/5 align-top">
                                    <td class="px-4 py-3 text-white">
                                        @php
                                            $changedAt = data_get($change, 'changed_at');
                                        @endphp
                                        {{ $changedAt ? \Illuminate\Support\Carbon::parse((string) $changedAt)->format('d M Y H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">
                                        {{ ucfirst(str_replace('_', ' ', (string) data_get($change, 'stage', 'update'))) }}
                                    </td>
                                    <td class="px-4 py-3 text-white">
                                        {{ data_get($change, 'changed_by_admin_name') ?? 'System' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-white">{{ data_get($change, 'old.channel_name') ?? '—' }}</div>
                                        <div class="text-xs text-slate-400">{{ data_get($change, 'old.account_number') ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-white">{{ data_get($change, 'new.channel_name') ?? '—' }}</div>
                                        <div class="text-xs text-slate-400">{{ data_get($change, 'new.account_number') ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">{{ data_get($change, 'reason') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Collateral Information --}}
        @if ($loan->loanProduct->category === 'collateral' && $loan->collateralLoanDetail)
            @php
                $collateral = $loan->collateralLoanDetail;
            @endphp
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Collateral Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Collateral Type:</span>
                            <span class="font-medium text-white">{{ $collateral->collateralType->name ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Collateral Value:</span>
                            <span class="font-medium text-white">ZMW {{ number_format($collateral->collateral_value, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">LTV Ratio:</span>
                            <span class="font-medium text-white">{{ number_format($collateral->loan_to_value_ratio, 2) }}%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Maximum Loan (LTV):</span>
                            <span class="font-medium text-emerald-400">ZMW {{ number_format($collateral->loan_to_value_amount, 2) }}</span>
                        </div>
                        @if ($collateral->serial_number)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Serial Number:</span>
                                <span class="font-medium text-white">{{ $collateral->serial_number }}</span>
                            </div>
                        @endif
                        @if ($collateral->item_quantity)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Quantity:</span>
                                <span class="font-medium text-white">{{ $collateral->item_quantity }}</span>
                            </div>
                        @endif
                        @if ($collateral->item_condition)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Condition:</span>
                                <span class="font-medium text-white capitalize">{{ $collateral->item_condition }}</span>
                            </div>
                        @endif
                        @if ($collateral->location)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Location:</span>
                                <span class="font-medium text-white">{{ $collateral->location }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Inspected:</span>
                            <span class="inline-block rounded-full px-2 py-1 text-xs {{ $collateral->is_inspected ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-300' }}">
                                {{ $collateral->is_inspected ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        @if ($collateral->is_inspected && $collateral->inspector)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Inspected By:</span>
                                <span class="font-medium text-white">{{ $collateral->inspector->full_name ?? '—' }}</span>
                            </div>
                        @endif
                        @if ($collateral->inspected_at)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400">Inspection Date:</span>
                                <span class="font-medium text-white">{{ $collateral->inspected_at->format('d M Y H:i') }}</span>
                            </div>
                        @endif
                        @if ($collateral->collateral_description)
                            <div>
                                <span class="text-slate-400">Description:</span>
                                <p class="font-medium text-white mt-1">{{ $collateral->collateral_description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
                
                {{-- Collateral Images --}}
                @if ($collateral->images && count($collateral->images) > 0)
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Collateral Images</h3>
                        <div class="grid grid-cols-5 gap-3">
                            @foreach ($collateral->images as $index => $imagePath)
                                <div class="relative group">
                                    <img src="{{ asset('storage/' . $imagePath) }}" 
                                         alt="Collateral Image {{ $index + 1 }}" 
                                         class="w-full h-24 object-cover rounded-lg border border-white/10">
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center">
                                        <button type="button" 
                                                onclick="openImageModal('{{ asset('storage/' . $imagePath) }}')"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-cyan-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-cyan-600 transition shadow-lg">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                            </svg>
                                            Preview
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Image Modal --}}
            <div id="imageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm" onclick="closeImageModal()">
                <div class="relative max-w-6xl max-h-[95vh] p-4">
                    <button onclick="closeImageModal()" 
                            class="absolute top-4 right-4 z-10 rounded-full bg-black/50 hover:bg-black/70 p-2 text-white hover:text-cyan-400 transition backdrop-blur-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <div class="relative">
                        <img id="modalImage" 
                             src="" 
                             alt="Collateral Image" 
                             class="max-w-full max-h-[90vh] rounded-lg shadow-2xl object-contain mx-auto" 
                             onclick="event.stopPropagation()">
                    </div>
                    <p class="text-center text-white/60 text-sm mt-4">Click outside the image to close</p>
                </div>
            </div>
        @endif

        @if($paymentDetailsEditable)
            @can('loans.update-payment-details')
                <div id="paymentDetailsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-2xl shadow-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-white">Change Payment Details</h2>
                                <p class="mt-1 text-sm text-slate-400">Update the customer payout details {{ $paymentDetailsStageLabel }}.</p>
                            </div>
                            <button type="button" onclick="closePaymentDetailsModal()" class="text-slate-400 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <p class="text-sm text-slate-300 mb-4">A reason is required when the channel or account changes. The update will be added to the loan trail and the customer will be notified automatically.</p>

                        <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm">
                            <p class="text-slate-300 mb-3">Current customer disbursement destination</p>
                            @include('partials.admin.disbursement-destination-summary', ['loan' => $loan])
                        </div>

                        <form method="POST" action="{{ route('admin.loans.payment-details', $loan) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="form_action" value="payment-details">

                            @include('partials.disbursement-destination-fields', [
                                'channels' => $paymentChannels,
                                'financialInstitutions' => $financialInstitutions,
                                'selectedChannelId' => old('channel_id', $loan->channel_id),
                                'disbursementPhoneNumber' => old('disbursement_phone_number', $loan->disbursement_phone_number),
                                'disbursementFinancialInstitutionId' => old('disbursement_financial_institution_id', $loan->disbursement_financial_institution_id),
                                'disbursementFinancialInstitutionBranchId' => old('disbursement_financial_institution_branch_id', $loan->disbursement_financial_institution_branch_id),
                                'disbursementAccountHolderName' => old('disbursement_account_holder_name', $loan->disbursement_account_holder_name),
                                'disbursementAccountNumber' => old('disbursement_account_number', $loan->disbursement_account_number),
                                'disbursementNotes' => old('disbursement_notes', $loan->disbursement_notes),
                                'channelSelectId' => 'paymentDetailsChannelId',
                                'wrapperId' => 'paymentDetailsDestinationFields',
                            ])

                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Reason If Changed</label>
                                <textarea name="payment_change_reason" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Explain why the payment details are changing">{{ old('payment_change_reason') }}</textarea>
                                @error('payment_change_reason')
                                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center justify-between gap-3 pt-2">
                                <p class="text-xs text-slate-400">This change is available only {{ $paymentDetailsStageLabel }}.</p>
                                <div class="flex gap-2">
                                    <button type="button" onclick="closePaymentDetailsModal()" class="rounded-2xl border border-white/15 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-white/10 transition">
                                        Cancel
                                    </button>
                                    <button type="submit" class="rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-amber-500/40 hover:from-amber-600 hover:to-orange-700 transition">
                                        Save Payment Details
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        @endif

        {{-- Manual Disbursement Modal --}}
        @if(
            isset($disbursementType, $banks, $wallets) &&
            $disbursementType === 'manual' &&
            $loan->status === 'approved' &&
            $loan->disbursement_status === 'pending'
        )
            <div id="disbursementModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div id="disbursementModalContent" class="modal-content rounded-3xl border p-6 w-full max-w-5xl max-h-[90vh] shadow-2xl relative flex flex-col min-h-0">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="modal-title text-lg font-semibold text-white">Record Disbursement</h2>
                        <button type="button" onclick="closeDisbursementModal()" class="text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <p class="modal-text text-sm text-slate-300 mb-4">
                        Record the disbursement source and reference. Customer payout destination is shown above—update it via Change Payment Details if needed.
                    </p>

                    <form method="POST" action="{{ route('admin.loans.disburse', $loan) }}" class="flex flex-col min-h-0 flex-1">
                        @csrf
                        <input type="hidden" name="form_action" value="disburse">

                        <div class="flex-1 overflow-y-auto min-h-0 space-y-4 pr-1">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm space-y-3">
                                <p class="text-slate-300 font-medium">Customer disbursement destination</p>
                                @include('partials.admin.disbursement-destination-summary', ['loan' => $loan])
                                @can('loans.update-payment-details')
                                    @if($paymentDetailsEditable)
                                        <button
                                            type="button"
                                            onclick="openPaymentDetailsFromDisburse()"
                                            class="w-full inline-flex items-center justify-center gap-2 rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-200 hover:bg-amber-500/20 transition"
                                        >
                                            Change Payment Details
                                        </button>
                                    @endif
                                @endcan
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-300">Disbursement Date <span class="text-rose-400">*</span></label>
                                    <input type="date"
                                           name="disbursement_date"
                                           value="{{ old('disbursement_date', now()->toDateString()) }}"
                                           required
                                           class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                                    @error('disbursement_date')
                                        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-300">Reference Number <span class="text-rose-400">*</span></label>
                                    <input type="text"
                                           name="reference_number"
                                           value="{{ old('reference_number') }}"
                                           required
                                           class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40"
                                           placeholder="Bank or wallet reference">
                                    @error('reference_number')
                                        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-300">Paid From — Type <span class="text-rose-400">*</span></label>
                                    <select name="source_type"
                                            id="disbursement_source_type"
                                            required
                                            class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                                        <option value="bank" @selected(old('source_type', 'bank') === 'bank')>Bank</option>
                                        <option value="wallet" @selected(old('source_type') === 'wallet')>Wallet</option>
                                    </select>
                                    @error('source_type')
                                        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-slate-300">Paid From — Account <span class="text-rose-400">*</span></label>
                                    <select name="source_id"
                                            id="disbursement_source_id"
                                            required
                                            class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                                        <option value="">Select Account</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}"
                                                    data-type="bank"
                                                    data-balance="{{ $bank->current_balance }}"
                                                    @selected((string) old('source_id') === (string) $bank->id)>
                                                {{ $bank->display_name }} (Balance: {{ number_format($bank->current_balance, 2) }})
                                            </option>
                                        @endforeach
                                        @foreach($wallets as $wallet)
                                            <option value="{{ $wallet->id }}"
                                                    data-type="wallet"
                                                    data-balance="{{ $wallet->current_balance }}"
                                                    @selected((string) old('source_id') === (string) $wallet->id)>
                                                {{ $wallet->display_name }} (Balance: {{ number_format($wallet->current_balance, 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('source_id')
                                        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                    @enderror
                                    <p id="selectedAccountBalance" class="mt-2 text-xs text-slate-300 hidden">
                                        Current balance: <span class="font-semibold"></span>
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-sm font-medium text-slate-300">Description</label>
                                    <textarea name="description"
                                              rows="2"
                                              class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40"
                                              placeholder="Optional description or internal note about this disbursement">{{ old('description') }}</textarea>
                                    @error('description')
                                        <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4 mt-4">
                            <p class="text-xs text-slate-400">
                                Amount to disburse: <span class="font-semibold text-slate-100">ZMW {{ number_format($loan->principal_amount, 2) }}</span>
                            </p>
                            <div class="flex gap-2">
                                <button type="button"
                                        onclick="closeDisbursementModal()"
                                        class="rounded-2xl border border-white/15 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-white/10 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                        id="confirmDisbursementButton"
                                        class="rounded-2xl bg-gradient-to-r from-cyan-500 to-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/40 hover:from-cyan-600 hover:to-emerald-600 transition">
                                    Confirm Disbursement
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Loan Extension Modal --}}
        @can('loan.extend')
            <div id="extensionModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div class="modal-content rounded-3xl border p-6 w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="modal-title text-lg font-semibold text-white">Extend Loan</h2>
                        <button type="button" onclick="closeExtensionModal()" class="text-slate-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                        <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
                            <div>Outstanding: <span class="font-semibold text-white">ZMW {{ number_format($loan->outstanding_balance, 2) }}</span></div>
                            <div>Status: <span class="font-semibold text-white">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span></div>
                            <div>Used Extensions: <span class="font-semibold text-white">{{ $loan->loanExtensions->count() }}/3</span></div>
                            @if($loan->daily_rate || $loan->weekly_rate)
                                <div>
                                    Booked rate:
                                    <span class="font-semibold text-white">
                                        @if($loan->daily_rate)
                                            {{ rtrim(rtrim(number_format((float) $loan->daily_rate, 8, '.', ''), '0'), '.') }}/day
                                        @else
                                            {{ rtrim(rtrim(number_format((float) $loan->weekly_rate, 8, '.', ''), '0'), '.') }}/week
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form id="extensionForm" method="POST" action="{{ route('admin.loans.extend', $loan) }}" class="space-y-4">
                        @csrf

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-300">Extension Type <span class="text-rose-400">*</span></label>
                                <select name="extension_type" id="extension_type" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                                    @foreach($extensionTypeOptions as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}" @selected((string) old('extension_type', $typeValue === \App\Models\LoanExtension::TYPE_DUE_DATE_EXTENSION ? $typeValue : '') === (string) $typeValue)>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-300">Extension Period <span class="text-rose-400">*</span></label>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <input
                                        type="number"
                                        min="1"
                                        max="120"
                                        name="extension_period_value"
                                        value="{{ old('extension_period_value', 1) }}"
                                        required
                                        class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40"
                                    >
                                    <select name="extension_period_unit" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                                        <option value="days" @selected(old('extension_period_unit', 'days') === 'days')>Days</option>
                                        <option value="months" @selected(old('extension_period_unit') === 'months')>Months</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-300">Interest Mode <span class="text-rose-400">*</span></label>
                                <select name="interest_mode" id="interest_mode" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                                    @foreach($interestModeOptions as $modeValue => $modeLabel)
                                        <option value="{{ $modeValue }}" @selected((string) old('interest_mode', $modeValue === \App\Models\LoanExtension::INTEREST_MODE_CONFIGURED_RATE ? $modeValue : '') === (string) $modeValue)>
                                            {{ $modeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="interest_value_wrap">
                                <label id="interest_value_label" class="text-sm font-medium text-slate-300">Interest Value</label>
                                <input
                                    type="number"
                                    step="0.000001"
                                    min="0"
                                    name="interest_value"
                                    id="interest_value"
                                    value="{{ old('interest_value') }}"
                                    class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40"
                                    placeholder="Enter value based on selected interest mode"
                                >
                            </div>

                            <div id="new_installment_count_wrap" class="hidden">
                                <label class="text-sm font-medium text-slate-300">New Installment Count <span class="text-rose-400">*</span></label>
                                <input
                                    type="number"
                                    min="1"
                                    max="120"
                                    name="new_installment_count"
                                    id="new_installment_count"
                                    value="{{ old('new_installment_count') }}"
                                    class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40"
                                >
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-300">Notes</label>
                            <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Optional extension notes for audit trail">{{ old('notes') }}</textarea>
                        </div>

                        <div id="extensionPreviewPanel" class="hidden rounded-2xl border border-cyan-400/30 bg-cyan-500/10 p-4 text-sm text-slate-200">
                            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-300 mb-3">Extension preview</p>
                            <div id="extensionPreviewError" class="hidden mb-3 rounded-xl border border-rose-400/40 bg-rose-500/10 px-3 py-2 text-rose-200 text-sm"></div>
                            <div id="extensionPreviewContent" class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <span class="text-slate-400">Extension interest</span>
                                    <div id="previewExtensionInterest" class="font-semibold text-white">—</div>
                                </div>
                                <div>
                                    <span class="text-slate-400">Projected outstanding</span>
                                    <div id="previewProjectedOutstanding" class="font-semibold text-white">—</div>
                                </div>
                                <div>
                                    <span class="text-slate-400">Current outstanding</span>
                                    <div id="previewCurrentOutstanding" class="font-semibold text-white">—</div>
                                </div>
                                <div>
                                    <span class="text-slate-400">New last due date</span>
                                    <div id="previewNewDueDate" class="font-semibold text-white">—</div>
                                </div>
                                <div id="previewInstallmentWrap" class="hidden md:col-span-2">
                                    <span class="text-slate-400">Approx. per installment</span>
                                    <div id="previewPerInstallment" class="font-semibold text-white">—</div>
                                </div>
                                <div id="previewRateWrap" class="hidden md:col-span-2 text-xs text-slate-400">
                                    <span id="previewRateDetail"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 flex-wrap gap-3">
                            <p class="text-xs text-slate-400">
                                Maximum extensions per loan: <span class="font-semibold text-white">3</span>
                            </p>
                            <div class="flex gap-2 flex-wrap justify-end">
                                <button type="button" id="extensionCalculateBtn" onclick="calculateExtensionPreview()" class="rounded-2xl border border-cyan-500/50 bg-cyan-500/20 px-4 py-2 text-sm font-medium text-cyan-200 hover:bg-cyan-500/30 transition">
                                    Calculate
                                </button>
                                <button type="button" onclick="closeExtensionModal()" class="rounded-2xl border border-white/15 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-white/10 transition">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-amber-500/40 hover:from-amber-600 hover:to-orange-700 transition">
                                    Submit Extension
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        @push('styles')
        <style>
            /* Theme-aware styles for Disbursement Modal */
            body[data-theme="light"] #disbursementModal .modal-content {
                background-color: #ffffff;
                border-color: #e2e8f0;
            }
            body[data-theme="light"] #disbursementModal .modal-title {
                color: #0f172a;
            }
            body[data-theme="light"] #disbursementModal .modal-text {
                color: #1e293b;
            }
            body[data-theme="light"] #disbursementModal label {
                color: #1e293b;
            }
            body[data-theme="light"] #disbursementModal input,
            body[data-theme="light"] #disbursementModal select,
            body[data-theme="light"] #disbursementModal textarea {
                background-color: #f9fafb;
                border-color: #cbd5e1;
                color: #0f172a;
            }
            body[data-theme="light"] #extensionModal .modal-content {
                background-color: #ffffff;
                border-color: #e2e8f0;
            }
            body[data-theme="light"] #extensionModal .modal-title {
                color: #0f172a;
            }
            body[data-theme="light"] #extensionModal label {
                color: #1e293b;
            }
            body[data-theme="light"] #extensionModal input,
            body[data-theme="light"] #extensionModal select,
            body[data-theme="light"] #extensionModal textarea {
                background-color: #f9fafb;
                border-color: #cbd5e1;
                color: #0f172a;
            }

            body[data-theme="dark"] #disbursementModal .modal-content {
                background-color: #0f172a;
                border-color: rgba(255, 255, 255, 0.1);
            }
            body[data-theme="dark"] #disbursementModal .modal-title {
                color: #f8fafc;
            }
            body[data-theme="dark"] #disbursementModal .modal-text {
                color: #94a3b8;
            }
            body[data-theme="dark"] #extensionModal .modal-content {
                background-color: #0f172a;
                border-color: rgba(255, 255, 255, 0.1);
            }
            body[data-theme="dark"] #extensionModal .modal-title {
                color: #f8fafc;
            }
        </style>
        @endpush

        @push('scripts')
        <script>
            function openImageModal(imageSrc) {
                const modal = document.getElementById('imageModal');
                const modalImage = document.getElementById('modalImage');
                if (!modal || !modalImage) return;
                modalImage.src = imageSrc;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeImageModal() {
                const modal = document.getElementById('imageModal');
                if (!modal) return;
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }

            function openPaymentDetailsModal() {
                const modal = document.getElementById('paymentDetailsModal');
                if (!modal) return;
                modal.classList.remove('hidden');
            }

            function closePaymentDetailsModal() {
                const modal = document.getElementById('paymentDetailsModal');
                if (!modal) return;
                modal.classList.add('hidden');
            }

            function openDisbursementModal() {
                const modal = document.getElementById('disbursementModal');
                if (!modal) return;
                modal.classList.remove('hidden');
                filterDisbursementAccounts();
            }

            function closeDisbursementModal() {
                const modal = document.getElementById('disbursementModal');
                if (!modal) return;
                modal.classList.add('hidden');
            }

            function openPaymentDetailsFromDisburse() {
                closeDisbursementModal();
                if (typeof openPaymentDetailsModal === 'function') {
                    openPaymentDetailsModal();
                }
            }

            function openExtensionModal() {
                const modal = document.getElementById('extensionModal');
                if (!modal) return;
                modal.classList.remove('hidden');
                toggleExtensionFields();
            }

            function closeExtensionModal() {
                const modal = document.getElementById('extensionModal');
                if (!modal) return;
                modal.classList.add('hidden');
                resetExtensionPreview();
            }

            const extensionPreviewUrl = @json(route('admin.loans.extend.preview', $loan));
            const extensionInterestModeConfigured = {{ \App\Models\LoanExtension::INTEREST_MODE_CONFIGURED_RATE }};
            const extensionInterestModeCustom = {{ \App\Models\LoanExtension::INTEREST_MODE_CUSTOM_RATE }};
            const extensionInterestModeFixed = {{ \App\Models\LoanExtension::INTEREST_MODE_FIXED_AMOUNT }};
            const extensionTypeRestructure = {{ \App\Models\LoanExtension::TYPE_RESTRUCTURE }};

            function formatZmw(amount) {
                const value = Number(amount);
                if (Number.isNaN(value)) {
                    return '—';
                }
                return 'ZMW ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function formatPreviewDate(isoDate) {
                if (!isoDate) {
                    return '—';
                }
                const parsed = new Date(isoDate + 'T00:00:00');
                if (Number.isNaN(parsed.getTime())) {
                    return isoDate;
                }
                return parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            }

            function resetExtensionPreview() {
                const panel = document.getElementById('extensionPreviewPanel');
                const errorBox = document.getElementById('extensionPreviewError');
                if (panel) {
                    panel.classList.add('hidden');
                }
                if (errorBox) {
                    errorBox.classList.add('hidden');
                    errorBox.textContent = '';
                }
            }

            async function calculateExtensionPreview() {
                const form = document.getElementById('extensionForm');
                const panel = document.getElementById('extensionPreviewPanel');
                const errorBox = document.getElementById('extensionPreviewError');
                const calculateBtn = document.getElementById('extensionCalculateBtn');

                if (!form || !panel || !errorBox) {
                    return;
                }

                const interestMode = document.getElementById('interest_mode')?.value;
                const extensionType = document.getElementById('extension_type')?.value;
                const interestValue = document.getElementById('interest_value')?.value;

                if (
                    (interestMode === String(extensionInterestModeCustom) || interestMode === String(extensionInterestModeFixed))
                    && (interestValue === '' || interestValue === null)
                ) {
                    errorBox.textContent = 'Enter an interest value for the selected interest mode.';
                    errorBox.classList.remove('hidden');
                    panel.classList.remove('hidden');
                    return;
                }

                if (extensionType === String(extensionTypeRestructure)) {
                    const installmentCount = document.getElementById('new_installment_count')?.value;
                    if (!installmentCount) {
                        errorBox.textContent = 'Enter the new installment count for a restructure.';
                        errorBox.classList.remove('hidden');
                        panel.classList.remove('hidden');
                        return;
                    }
                }

                const formData = new FormData(form);
                const payload = {};
                formData.forEach((value, key) => {
                    if (key !== '_token' && key !== 'notes') {
                        payload[key] = value;
                    }
                });

                if (calculateBtn) {
                    calculateBtn.disabled = true;
                    calculateBtn.textContent = 'Calculating…';
                }

                errorBox.classList.add('hidden');
                errorBox.textContent = '';

                try {
                    const response = await fetch(extensionPreviewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token'),
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();

                    if (!response.ok || !data.eligible) {
                        errorBox.textContent = data.message || 'Unable to calculate extension preview.';
                        errorBox.classList.remove('hidden');
                        panel.classList.remove('hidden');
                        return;
                    }

                    const projected = data.projected || {};
                    const interest = data.interest || {};
                    const rates = data.configured_rates || {};

                    document.getElementById('previewExtensionInterest').textContent = formatZmw(projected.extension_interest);
                    document.getElementById('previewProjectedOutstanding').textContent = formatZmw(projected.projected_outstanding);
                    document.getElementById('previewCurrentOutstanding').textContent = formatZmw(projected.current_outstanding);
                    document.getElementById('previewNewDueDate').textContent = formatPreviewDate(projected.new_last_due_date);

                    const installmentWrap = document.getElementById('previewInstallmentWrap');
                    const perInstallment = document.getElementById('previewPerInstallment');
                    if (installmentWrap && perInstallment) {
                        if (projected.per_installment) {
                            installmentWrap.classList.remove('hidden');
                            perInstallment.textContent = formatZmw(projected.per_installment) + ' × ' + (projected.new_installment_count || '—') + ' installments';
                        } else {
                            installmentWrap.classList.add('hidden');
                            perInstallment.textContent = '—';
                        }
                    }

                    const rateWrap = document.getElementById('previewRateWrap');
                    const rateDetail = document.getElementById('previewRateDetail');
                    if (rateWrap && rateDetail && String(data.interest_mode) === String(extensionInterestModeConfigured)) {
                        const parts = [];
                        if (rates.display_daily_rate) {
                            parts.push('Daily rate: ' + rates.display_daily_rate);
                        }
                        if (rates.display_weekly_rate) {
                            parts.push('Weekly rate: ' + rates.display_weekly_rate);
                        }
                        if (interest.interest_rate) {
                            parts.push('Effective: ' + Number(interest.interest_rate).toFixed(4) + '%');
                        }
                        if (interest.base_amount) {
                            parts.push('Base: ' + formatZmw(interest.base_amount));
                        }
                        if (parts.length) {
                            rateDetail.textContent = parts.join(' · ') + ' (' + (rates.source === 'loan_snapshot' ? 'booked on loan' : 'from rate card') + ')';
                            rateWrap.classList.remove('hidden');
                        } else {
                            rateWrap.classList.add('hidden');
                        }
                    } else if (rateWrap) {
                        rateWrap.classList.add('hidden');
                    }

                    panel.classList.remove('hidden');
                } catch (error) {
                    errorBox.textContent = 'Failed to calculate preview. Please try again.';
                    errorBox.classList.remove('hidden');
                    panel.classList.remove('hidden');
                } finally {
                    if (calculateBtn) {
                        calculateBtn.disabled = false;
                        calculateBtn.textContent = 'Calculate';
                    }
                }
            }

            function toggleExtensionFields() {
                const extensionType = document.getElementById('extension_type');
                const interestMode = document.getElementById('interest_mode');
                const interestValueWrap = document.getElementById('interest_value_wrap');
                const interestValue = document.getElementById('interest_value');
                const interestValueLabel = document.getElementById('interest_value_label');
                const installmentWrap = document.getElementById('new_installment_count_wrap');
                const installmentInput = document.getElementById('new_installment_count');

                if (!extensionType || !interestMode || !interestValueWrap || !interestValue || !interestValueLabel || !installmentWrap || !installmentInput) {
                    return;
                }

                const selectedMode = interestMode.value;
                const selectedType = extensionType.value;

                const requiresInterestValue = selectedMode === '{{ \App\Models\LoanExtension::INTEREST_MODE_CUSTOM_RATE }}'
                    || selectedMode === '{{ \App\Models\LoanExtension::INTEREST_MODE_FIXED_AMOUNT }}';

                if (requiresInterestValue) {
                    interestValueWrap.classList.remove('hidden');
                    interestValue.required = true;
                    interestValueLabel.textContent = selectedMode === '{{ \App\Models\LoanExtension::INTEREST_MODE_CUSTOM_RATE }}'
                        ? 'Interest Value (%) *'
                        : 'Interest Amount (ZMW) *';
                } else {
                    interestValueWrap.classList.add('hidden');
                    interestValue.required = false;
                    interestValue.value = '';
                }

                const requiresInstallments = selectedType === '{{ \App\Models\LoanExtension::TYPE_RESTRUCTURE }}';
                if (requiresInstallments) {
                    installmentWrap.classList.remove('hidden');
                    installmentInput.required = true;
                } else {
                    installmentWrap.classList.add('hidden');
                    installmentInput.required = false;
                    installmentInput.value = '';
                }
            }

            function updateSelectedAccountBalance() {
                const accountSelect = document.getElementById('disbursement_source_id');
                const balanceText = document.getElementById('selectedAccountBalance');

                if (!accountSelect || !balanceText) {
                    return;
                }

                const selected = accountSelect.options[accountSelect.selectedIndex];
                const balance = selected ? selected.getAttribute('data-balance') : null;

                if (balance !== null && balance !== '') {
                    balanceText.querySelector('span').textContent = 'ZMW ' + Number(balance).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    balanceText.classList.remove('hidden');
                } else {
                    balanceText.classList.add('hidden');
                    balanceText.querySelector('span').textContent = '';
                }
            }

            function filterDisbursementAccounts() {
                const typeSelect = document.getElementById('disbursement_source_type');
                const accountSelect = document.getElementById('disbursement_source_id');
                if (!typeSelect || !accountSelect) return;

                const type = typeSelect.value;
                const options = Array.from(accountSelect.querySelectorAll('option'));
                const currentValue = accountSelect.value;

                options.forEach(option => {
                    if (option.value === '') {
                        option.style.display = 'block';
                    } else {
                        const optionType = option.getAttribute('data-type');
                        option.style.display = optionType === type ? 'block' : 'none';
                    }
                });

                const selectedOption = options.find(option => option.value === currentValue);
                if (!selectedOption || selectedOption.getAttribute('data-type') !== type) {
                    accountSelect.value = '';
                }

                updateSelectedAccountBalance();
            }

            document.addEventListener('DOMContentLoaded', function () {
                const typeSelect = document.getElementById('disbursement_source_type');
                const accountSelect = document.getElementById('disbursement_source_id');
                const balanceText = document.getElementById('selectedAccountBalance');

                if (typeSelect) {
                    typeSelect.addEventListener('change', filterDisbursementAccounts);
                    filterDisbursementAccounts();
                }

                const extensionType = document.getElementById('extension_type');
                const interestMode = document.getElementById('interest_mode');
                if (extensionType) {
                    extensionType.addEventListener('change', toggleExtensionFields);
                }
                if (interestMode) {
                    interestMode.addEventListener('change', toggleExtensionFields);
                }
                toggleExtensionFields();

                if (accountSelect && balanceText) {
                    accountSelect.addEventListener('change', updateSelectedAccountBalance);
                    updateSelectedAccountBalance();
                }

                // Prevent submission if balance is less than principal amount
                const disbursementForm = document.querySelector('#disbursementModal form');
                const principalAmount = {{ (float) $loan->principal_amount }};
                if (disbursementForm && accountSelect) {
                    const confirmButton = document.getElementById('confirmDisbursementButton');
                    disbursementForm.addEventListener('submit', function (e) {
                        if (confirmButton) {
                            confirmButton.disabled = true;
                            confirmButton.classList.add('opacity-50', 'cursor-not-allowed');
                            confirmButton.textContent = 'Processing...';
                        }

                        const selected = accountSelect.options[accountSelect.selectedIndex];
                        const balanceAttr = selected ? selected.getAttribute('data-balance') : null;
                        const balance = balanceAttr !== null ? parseFloat(balanceAttr) : NaN;

                        if (!isNaN(balance) && balance < principalAmount) {
                            e.preventDefault();
                            if (confirmButton) {
                                confirmButton.disabled = false;
                                confirmButton.classList.remove('opacity-50', 'cursor-not-allowed');
                                confirmButton.textContent = 'Confirm Disbursement';
                            }
                            const message = 'The selected account does not have enough balance to disburse this loan. ' +
                                'Required: ZMW ' + principalAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
                                ', Available: ZMW ' + balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.';

                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Insufficient Balance',
                                    text: message,
                                    confirmButtonColor: '#ef4444',
                                });
                            } else {
                                alert(message);
                            }
                        }
                    });
                }

                const paymentDetailsModal = document.getElementById('paymentDetailsModal');
                if (paymentDetailsModal) {
                    paymentDetailsModal.addEventListener('click', function (e) {
                        if (e.target === this) {
                            closePaymentDetailsModal();
                        }
                    });
                }

                // Close disbursement modal on outside click
                const disbursementModal = document.getElementById('disbursementModal');
                if (disbursementModal) {
                    disbursementModal.addEventListener('click', function (e) {
                        if (e.target === this) {
                            closeDisbursementModal();
                        }
                    });
                }

                const extensionModal = document.getElementById('extensionModal');
                if (extensionModal) {
                    extensionModal.addEventListener('click', function (e) {
                        if (e.target === this) {
                            closeExtensionModal();
                        }
                    });
                }

                @if($errors->any() && old('form_action') === 'payment-details')
                    openPaymentDetailsModal();
                @endif

                @if($errors->any() && old('form_action') === 'disburse')
                    openDisbursementModal();
                @endif

                @if($errors->any() && old('form_action') === 'approve')
                    showApproveModal();
                @endif

                @if($errors->any() && old('extension_type'))
                    openExtensionModal();
                @endif
            });

            // Close modals on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeImageModal();
                    closePaymentDetailsModal();
                    closeDisbursementModal();
                    closeExtensionModal();
                }
            });
        </script>
        @endpush

        {{-- Loan Extensions --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Loan Extensions</h2>
                <span class="text-xs text-slate-400">Maximum 3 extensions per loan</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="bg-slate-100 text-center text-sm font-semibold uppercase tracking-[0.2em] border-b border-slate-300">
                            <th class="px-4 py-3 text-left text-slate-800">Date</th>
                            <th class="px-4 py-3 text-left text-slate-800">Type</th>
                            <th class="px-4 py-3 text-left text-slate-800">Interest</th>
                            <th class="px-4 py-3 text-left text-slate-800">Old Due Date</th>
                            <th class="px-4 py-3 text-left text-slate-800">New Due Date</th>
                            <th class="px-4 py-3 text-left text-slate-800">Admin</th>
                            <th class="px-4 py-3 text-left text-slate-800">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loan->loanExtensions as $extension)
                            <tr class="border-t border-white/5">
                                <td class="px-4 py-3 text-white">
                                    {{ $extension->created_at?->format('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-white">{{ $extension->type_label }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-white font-semibold">ZMW {{ number_format((float) $extension->interest_amount, 2) }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ $extension->interest_mode_label }}
                                        @if(!is_null($extension->interest_rate))
                                            ({{ number_format((float) $extension->interest_rate, 4) }}%)
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ $extension->old_due_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $extension->new_due_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $extension->creator?->full_name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $extension->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                                    No extensions recorded for this loan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.loans.partials.settlement-panel')

        {{-- Repayment Schedule --}}
        @if ($loan->first_payment_date && $loan->tenure_months > 0)
            @php
                $repaymentSchedule = $loan->getRepaymentSchedule();
                $hasScheduleComponents = collect($repaymentSchedule)->contains(
                    fn ($row) => ($row['principal_component'] ?? null) !== null
                        || ($row['interest_component'] ?? null) !== null
                );
                $scheduleUsesProjected = $loan->scheduleUsesProjectedInterest();
            @endphp
            @if (!empty($repaymentSchedule))
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-xl font-semibold text-white">Repayment Schedule</h2>
                            @if ($scheduleUsesProjected)
                                <p class="mt-1 text-xs text-amber-200/90 max-w-xl">Installment interest is projected (full-term disclosure). Booked outstanding excludes unearned interest.</p>
                            @endif
                        </div>
                        <a href="{{ route('admin.loans.schedule-pdf', $loan) }}" target="_blank" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Export PDF Schedule
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full w-full text-sm text-slate-300">
                            <thead>
                                <tr class="bg-slate-100 text-center text-sm font-semibold uppercase tracking-[0.25em] border-b border-slate-300">
                                    <th class="px-4 py-4 text-base text-slate-800 font-bold">Period</th>
                                    <th class="px-4 py-4 text-base text-slate-800 font-bold">Payment Date</th>
                                    <th class="px-4 py-4 text-base text-slate-800 font-bold">Expected</th>
                                    @if ($hasScheduleComponents)
                                        <th class="px-4 py-4 text-base text-slate-800 font-bold">Principal</th>
                                        <th class="px-4 py-4 text-base text-slate-800 font-bold">Fee</th>
                                        <th class="px-4 py-4 text-base text-slate-800 font-bold">Interest</th>
                                        <th class="px-4 py-4 text-base text-slate-800 font-bold">Basis</th>
                                    @endif
                                    <th class="px-4 py-4 text-base text-slate-800 font-bold">Paid</th>
                                    <th class="px-4 py-4 text-base text-slate-800 font-bold">Remaining</th>
                                    <th class="px-4 py-4 text-base text-slate-800 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($repaymentSchedule as $scheduleItem)
                                    @php
                                        $statusColors = [
                                            'paid' => 'bg-emerald-500/20 text-emerald-300',
                                            'paid_early' => 'bg-blue-500/20 text-blue-300',
                                            'partial' => 'bg-amber-500/20 text-amber-300',
                                            'overdue' => 'bg-rose-500/20 text-rose-300',
                                            'upcoming' => 'bg-slate-500/20 text-slate-300',
                                        ];
                                        $statusColor = $statusColors[$scheduleItem['status']] ?? 'bg-slate-500/20 text-slate-300';
                                        $statusLabels = [
                                            'paid' => 'Paid',
                                            'paid_early' => 'Paid Early',
                                            'partial' => 'Partial',
                                            'overdue' => 'Overdue',
                                            'upcoming' => 'Upcoming',
                                        ];
                                        $statusLabel = $statusLabels[$scheduleItem['status']] ?? ucfirst($scheduleItem['status']);
                                    @endphp
                                    <tr class="border-t border-white/5 text-center {{ $scheduleItem['is_overdue'] ? 'bg-rose-500/5' : '' }}">
                                        <td class="px-4 py-3 font-medium text-white">
                                            {{ $scheduleItem['period'] }}/{{ $loan->tenure_months }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-white">{{ $scheduleItem['payment_date']->format('d M Y') }}</div>
                                            @if($scheduleItem['payment_date']->isPast() && $scheduleItem['status'] !== 'paid')
                                                <div class="text-xs text-rose-400">{{ $scheduleItem['payment_date']->diffForHumans() }}</div>
                                            @elseif($scheduleItem['payment_date']->isFuture())
                                                <div class="text-xs text-slate-400">{{ $scheduleItem['payment_date']->diffForHumans() }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-white">
                                            ZMW {{ number_format($scheduleItem['expected_amount'], 2) }}
                                            @if (!empty($scheduleItem['is_projected_interest']))
                                                <div class="text-[10px] text-amber-400/80 uppercase tracking-wide">proj. interest</div>
                                            @endif
                                        </td>
                                        @if ($hasScheduleComponents)
                                            <td class="px-4 py-3 text-xs">{{ isset($scheduleItem['principal_component']) ? 'ZMW '.number_format($scheduleItem['principal_component'], 2) : '—' }}</td>
                                            <td class="px-4 py-3 text-xs">{{ isset($scheduleItem['fee_component']) ? 'ZMW '.number_format($scheduleItem['fee_component'], 2) : '—' }}</td>
                                            <td class="px-4 py-3 text-xs">{{ isset($scheduleItem['interest_component']) ? 'ZMW '.number_format($scheduleItem['interest_component'], 2) : '—' }}</td>
                                            <td class="px-4 py-3 text-xs text-slate-400">{{ $scheduleItem['schedule_basis'] ?? '—' }}</td>
                                        @endif
                                        <td class="px-4 py-3 {{ $scheduleItem['amount_paid'] > 0 ? 'text-emerald-400 font-medium' : 'text-slate-500' }}">
                                            @if($scheduleItem['amount_paid'] > 0)
                                                ZMW {{ number_format($scheduleItem['amount_paid'], 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 {{ $scheduleItem['remaining_amount'] > 0 ? 'text-amber-400 font-medium' : 'text-slate-500' }}">
                                            @if($scheduleItem['remaining_amount'] > 0)
                                                ZMW {{ number_format($scheduleItem['remaining_amount'], 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-block rounded-full px-3 py-1 text-xs font-medium {{ $statusColor }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-cyan-500/30 bg-gradient-to-r from-cyan-500/10 to-blue-500/10">
                                    <td colspan="2" class="px-4 py-4 text-right font-semibold text-white">
                                        Schedule total{{ $scheduleUsesProjected ? ' (projected)' : '' }}:
                                    </td>
                                    <td class="px-4 py-4 text-center font-bold text-white" colspan="{{ $hasScheduleComponents ? 5 : 1 }}">
                                        ZMW {{ number_format($loan->getScheduleExpectedTotal(), 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-center font-medium text-emerald-400">
                                        ZMW {{ number_format($loan->amount_paid, 2) }}
                                    </td>
                                    <td class="px-4 py-4 text-center font-medium text-amber-400" title="{{ $scheduleUsesProjected ? 'Sum of installment remainings (may differ from booked outstanding when interest accrues daily)' : 'Sum of installment remainings' }}">
                                        ZMW {{ number_format(collect($repaymentSchedule)->sum('remaining_amount'), 2) }}
                                    </td>
                                    <td class="px-4 py-4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        {{-- Accruals Table --}}
        @if ($loan->accruals->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Interest Accruals</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-center border-b-2 border-cyan-500/30 bg-gradient-to-r from-cyan-500/20 to-blue-500/20">
                                <th class="px-4 py-4 text-base text-white font-bold">Accrual Date</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Principal Balance</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Daily Interest</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Cumulative Interest</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Total Balance</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Rate Used (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loan->accruals as $accrual)
                                <tr class="border-t border-white/5 text-center">
                                    <td class="px-4 py-3">{{ $accrual->accrual_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3">ZMW {{ number_format($accrual->principal_balance, 2) }}</td>
                                    <td class="px-4 py-3">ZMW {{ number_format($accrual->interest_amount, 2) }}</td>
                                    <td class="px-4 py-3">ZMW {{ number_format($accrual->cumulative_interest, 2) }}</td>
                                    <td class="px-4 py-3 font-medium text-white">ZMW {{ number_format($accrual->total_balance, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-400">{{ number_format($accrual->rate_used * 100, 6) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Repayments Table --}}
        @if ($loan->loanRepayments->isNotEmpty())
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Repayment History</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-center border-b-2 border-cyan-500/30 bg-gradient-to-r from-cyan-500/20 to-blue-500/20">
                                <th class="px-4 py-4 text-base text-white font-bold">Repayment #</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Type</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Recovery Method</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Date</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Amount</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Principal</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Interest</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Processing Fee</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Balance Before</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Balance After</th>
                                <th class="px-4 py-4 text-base text-white font-bold">Reference</th>
                                @if ($canRefundRepayments)
                                    <th class="px-4 py-4 text-base text-white font-bold">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loan->loanRepayments->sortByDesc('created_at') as $loanRepayment)
                                @php
                                    $repayment = $loanRepayment->repayment;
                                    $isRefund = $loanRepayment->isRefund();
                                    $refundableRemaining = $loanRepayment->refundableAmountRemaining();
                                @endphp
                                <tr class="border-t border-white/5 text-center {{ $isRefund ? 'bg-rose-500/5' : '' }}">
                                    <td class="px-4 py-3 font-medium text-white">
                                        {{ $repayment->repayment_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($isRefund)
                                            <span class="inline-flex rounded-full bg-rose-500/20 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-rose-300">Refund</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-emerald-500/20 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-300">Payment</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">
                                        {{ $repayment->recoveryMethodLabel() }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-400">
                                        {{ ($repayment->processed_at ?? $repayment->created_at)->format('d M Y') }}
                                        <div class="text-xs text-slate-500">{{ ($repayment->processed_at ?? $repayment->created_at)->format('g:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-medium {{ $isRefund ? 'text-rose-300' : 'text-white' }}">
                                        ZMW {{ number_format($loanRepayment->amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 {{ $isRefund ? 'text-rose-400/80' : 'text-green-400' }}">
                                        ZMW {{ number_format($loanRepayment->principal_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 {{ $isRefund ? 'text-rose-400/80' : 'text-amber-400' }}">
                                        ZMW {{ number_format($loanRepayment->interest_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 {{ $isRefund ? 'text-rose-400/80' : 'text-blue-400' }}">
                                        ZMW {{ number_format($loanRepayment->processing_fee_amount, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-400">
                                        ZMW {{ number_format($loanRepayment->outstanding_balance_before, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-white">
                                        ZMW {{ number_format($loanRepayment->outstanding_balance_after, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-400">
                                        @if ($isRefund && $loanRepayment->refundOf)
                                            <div>Against {{ $loanRepayment->refundOf->repayment?->repayment_number ?? 'payment #'.$loanRepayment->refund_of_loan_repayment_id }}</div>
                                            @if ($loanRepayment->notes)
                                                <div class="mt-1 text-slate-500">{{ Str::limit($loanRepayment->notes, 60) }}</div>
                                            @endif
                                        @else
                                            <div class="font-mono">
                                                {{ $repayment->external_reference ? substr($repayment->external_reference, 0, 15) . '...' : '—' }}
                                            </div>
                                        @endif
                                    </td>
                                    @if ($canRefundRepayments)
                                        <td class="px-4 py-3">
                                            @if ($loanRepayment->isPayment() && $refundableRemaining > 0)
                                                <button
                                                    type="button"
                                                    onclick="openRefundModal({{ $loanRepayment->id }}, '{{ $repayment->repayment_number }}', {{ number_format($refundableRemaining, 2, '.', '') }})"
                                                    class="inline-flex items-center rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-200 hover:bg-rose-500/20 transition"
                                                >
                                                    Refund
                                                </button>
                                            @else
                                                <span class="text-slate-600">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($canRefundRepayments)
                <div id="refundModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-lg shadow-2xl">
                        <h3 class="text-xl font-semibold text-white mb-2">Record Refund</h3>
                        <p class="text-sm text-slate-300 mb-4">
                            Issue a refund against the selected payment. The original repayment record is preserved for audit.
                        </p>
                        <form method="POST" action="{{ route('admin.loans.refund', $loan) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="loan_repayment_id" id="refund_loan_repayment_id" value="{{ old('loan_repayment_id') }}">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">Payment reference</label>
                                <p id="refund_payment_reference" class="text-white font-semibold">—</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">Maximum refundable</label>
                                <p id="refund_max_amount" class="text-emerald-300 font-semibold">ZMW 0.00</p>
                            </div>
                            <div>
                                <label for="refund_amount" class="block text-sm font-medium text-slate-300 mb-1">Refund amount (ZMW)</label>
                                <input
                                    type="number"
                                    name="amount"
                                    id="refund_amount"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    value="{{ old('amount') }}"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-rose-400 focus:ring-rose-400/40"
                                >
                                @error('amount')
                                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="refund_reason" class="block text-sm font-medium text-slate-300 mb-1">Reason (required)</label>
                                <textarea
                                    name="reason"
                                    id="refund_reason"
                                    rows="3"
                                    required
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-rose-400 focus:ring-rose-400/40"
                                    placeholder="e.g. Customer overpaid by ZMW 300"
                                >{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                                @error('loan_repayment_id')
                                    <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onclick="closeRefundModal()"
                                    class="flex-1 rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/5 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="flex-1 rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-500 transition"
                                >
                                    Confirm Refund
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    function openRefundModal(loanRepaymentId, repaymentNumber, maxRefundable) {
                        document.getElementById('refund_loan_repayment_id').value = loanRepaymentId;
                        document.getElementById('refund_payment_reference').textContent = repaymentNumber;
                        document.getElementById('refund_max_amount').textContent = 'ZMW ' + Number(maxRefundable).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const amountInput = document.getElementById('refund_amount');
                        amountInput.max = maxRefundable;
                        amountInput.value = maxRefundable;
                        document.getElementById('refundModal').classList.remove('hidden');
                    }
                    function closeRefundModal() {
                        document.getElementById('refundModal').classList.add('hidden');
                    }
                    @if ($errors->has('amount') || $errors->has('reason') || $errors->has('loan_repayment_id'))
                        document.addEventListener('DOMContentLoaded', function () {
                            const id = document.getElementById('refund_loan_repayment_id').value;
                            if (id) {
                                document.getElementById('refundModal').classList.remove('hidden');
                            }
                        });
                    @endif
                </script>
            @endif
        @endif
    </div>

    @if ($loan->status === 'pending_approval')
        <!-- Approve Modal -->
        <div id="approveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-2xl shadow-2xl">
                <h3 class="text-xl font-semibold text-white mb-2">Approve Loan</h3>
                <p class="text-sm text-slate-300 mb-4">Confirm approval for this loan. Update payout details separately if needed before approving.</p>

                <div class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm space-y-3">
                    <p class="text-slate-300 font-medium">Customer disbursement destination</p>
                    @include('partials.admin.disbursement-destination-summary', ['loan' => $loan])
                    @can('loans.update-payment-details')
                        @if($paymentDetailsEditable)
                            <button
                                type="button"
                                onclick="openPaymentDetailsFromApprove()"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-200 hover:bg-amber-500/20 transition"
                            >
                                Change Payment Details
                            </button>
                        @endif
                    @endcan
                </div>

                <form id="approveForm" method="POST" action="{{ route('admin.approvals.loans.approve', $loan) }}">
                    @csrf
                    <input type="hidden" name="redirect_to_loan" value="1">
                    <input type="hidden" name="form_action" value="approve">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Approval Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Optional note for this approval...">{{ old('notes') }}</textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeApproveModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 rounded-2xl border border-emerald-200/40 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-700 hover:to-teal-700 transition">
                            Confirm Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-xl font-semibold text-white mb-4">Reject Loan</h3>
                <form id="rejectForm" method="POST" action="{{ route('admin.approvals.loans.reject', $loan) }}">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Rejection Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeRejectModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-500/30 hover:bg-rose-700 transition">
                            Confirm Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @push('scripts')
        <script>
            function showApproveModal() {
                document.getElementById('approveModal').classList.remove('hidden');
            }

            function closeApproveModal() {
                document.getElementById('approveModal').classList.add('hidden');
                document.getElementById('approveForm').reset();
            }

            function openPaymentDetailsFromApprove() {
                closeApproveModal();
                if (typeof openPaymentDetailsModal === 'function') {
                    openPaymentDetailsModal();
                }
            }

            function showRejectModal(loanId) {
                document.getElementById('rejectModal').classList.remove('hidden');
            }

            function closeRejectModal() {
                document.getElementById('rejectModal').classList.add('hidden');
                document.getElementById('rejectForm').reset();
            }

            // Close modal on outside click
            document.getElementById('approveModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeApproveModal();
                }
            });

            document.getElementById('rejectModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeRejectModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeApproveModal();
                    closeRejectModal();
                }
            });
        </script>
        @endpush
    @endif
@endsection
