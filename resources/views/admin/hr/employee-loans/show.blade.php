@extends('layouts.admin')

@section('title', $loan->loan_number.' | Employee Loan')

@section('content')
@php
    $employee = $loan->employee;
    $canViewPayoutNumbers = auth('admin')->user()?->can('hr.employee-loans.financials');
    $canApprovePending = $loan->status === 'pending_approval' && auth('admin')->user()?->can('approve', $loan);
    $rateProductName = $loan->loanRate?->loanRateType?->name ?? 'Employee loan';

    $statusColors = [
        'draft' => 'status-pill status-pill-cancelled',
        'pending_approval' => 'status-pill status-pill-pending',
        'approved' => 'status-pill status-pill-approved',
        'active' => 'status-pill status-pill-active',
        'settled' => 'status-pill status-pill-settled',
        'rejected' => 'status-pill status-pill-defaulted',
        'cancelled' => 'status-pill status-pill-cancelled',
    ];
    $statusColor = $statusColors[$loan->status] ?? 'status-pill status-pill-cancelled';

    $disbursementStatusColors = [
        'completed' => 'status-pill status-pill-completed',
        'processing' => 'bg-blue-500/20 text-blue-300',
        'failed' => 'bg-rose-500/20 text-rose-300',
        'pending' => 'bg-amber-500/20 text-amber-300',
    ];
    $disbursementStatusColor = $disbursementStatusColors[$loan->disbursement_status] ?? 'bg-amber-500/20 text-amber-300';
    $disbursementUsesStatusPill = $loan->disbursement_status === 'completed';
@endphp

<div class="space-y-8">
    @if (session('status'))
        <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-1">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">HR · Employee Loans</p>
            <h1 class="text-3xl font-bold">{{ $loan->loan_number }}</h1>
            <p class="text-sm text-slate-400">{{ $employee?->full_name ?? 'Employee' }} · {{ ucfirst(str_replace('_', ' ', $loan->status)) }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            @can('update', $loan)
                @if($loan->status === 'draft')
                    <a href="{{ route('admin.hr.employee-loans.edit', $loan) }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-white hover:bg-white/10 transition">
                        Edit draft
                    </a>
                    <a href="{{ route('admin.hr.employee-loans.verify', $loan) }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-cyan-200/40 bg-gradient-to-r from-slate-800 to-cyan-700 px-4 py-3 font-semibold text-white shadow-lg shadow-slate-900/40 hover:from-slate-900 hover:to-cyan-800 transition">
                        Review & submit
                    </a>
                @endif
            @endcan

            @if($canApprovePending)
                <button type="button" onclick="showEmployeeLoanApproveModal()"
                        class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200/40 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-700 hover:to-teal-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Approve loan
                </button>
                <button type="button" onclick="showEmployeeLoanRejectModal()" class="btn-reject-critical">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reject loan
                </button>
            @endif

            @can('disburse', $loan)
                @if($loan->status === 'approved' && in_array($loan->disbursement_status, ['pending', 'failed'], true))
                    <button type="button" onclick="openEmployeeLoanDisbursementModal()"
                            class="inline-flex items-center gap-2 rounded-2xl border border-cyan-200/40 bg-gradient-to-r from-slate-800 to-cyan-700 px-4 py-3 font-semibold text-white shadow-lg shadow-slate-900/40 hover:from-slate-900 hover:to-cyan-800 transition">
                        Record disbursement
                    </button>
                @endif
                @if(
                    ($disbursementGatewayAvailable ?? false) &&
                    $loan->status === 'approved' &&
                    in_array($loan->disbursement_status, ['pending', 'failed'], true) &&
                    ($disbursementDestinationPreview ?? null)
                )
                    <form method="POST" action="{{ route('admin.hr.employee-loans.disburse.gateway', $loan) }}" class="inline"
                          onsubmit="return confirm('Submit disbursement of K {{ number_format((float) $loan->principal_amount, 2) }} via cGrate to {{ $disbursementDestinationPreview['customer_account'] }} ({{ $disbursementDestinationPreview['issuer_name'] }})?');">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200/40 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-700 hover:to-teal-700 transition">
                            Disburse via cGrate
                        </button>
                    </form>
                @endif
            @endcan

            @can('repay', $loan)
                @if($loan->isActive())
                    <a href="#repayment-panel"
                       class="inline-flex items-center gap-2 rounded-2xl border border-emerald-300/40 bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-600 hover:to-teal-700 transition">
                        Record repayment
                    </a>
                @endif
            @endcan

            @can('delete', $loan)
                @if($loan->status === 'draft')
                    <form method="POST" action="{{ route('admin.hr.employee-loans.destroy', $loan) }}" class="inline" onsubmit="return confirm('Delete this draft permanently?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-rose-300/40 bg-gradient-to-r from-rose-700 to-red-700 px-4 py-3 font-semibold text-white shadow-lg shadow-rose-500/30 hover:from-rose-800 hover:to-red-800 transition">
                            Delete draft
                        </button>
                    </form>
                @endif
            @endcan

            @can('update', $loan)
                @if(in_array($loan->status, ['draft', 'pending_approval', 'approved'], true))
                    <form method="POST" action="{{ route('admin.hr.employee-loans.cancel', $loan) }}" class="inline" onsubmit="return confirm('Cancel this loan?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                            Cancel loan
                        </button>
                    </form>
                @endif
            @endcan

            <a href="{{ route('admin.hr.employee-loans.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                Back to list
            </a>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h2 class="text-xl font-semibold text-white">Loan details</h2>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Loan number</span>
                    <span class="font-medium text-white">{{ $loan->loan_number }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Status</span>
                    <span class="{{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Product</span>
                    <span class="font-medium text-white">{{ $rateProductName }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Tenure</span>
                    <span class="font-medium text-white">{{ $loan->tenure_months }} {{ $loan->tenure_months === 1 ? 'month' : 'months' }}</span>
                </div>
                @if($loan->application_date)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Application date</span>
                        <span class="font-medium text-white">{{ $loan->application_date->format('d M Y') }}</span>
                    </div>
                @endif
                @if($loan->loan_start_date)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Start date</span>
                        <span class="font-medium text-white">{{ $loan->loan_start_date->format('d M Y') }}</span>
                    </div>
                @endif
                @if($loan->loan_end_date)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">End date</span>
                        <span class="font-medium text-white">{{ $loan->loan_end_date->format('d M Y') }}</span>
                    </div>
                @endif
                @if($loan->first_payment_date)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">First payment date</span>
                        <span class="font-medium text-white">{{ $loan->first_payment_date->format('d M Y') }}</span>
                    </div>
                @endif
                @if($loan->purpose)
                    <div class="border-t border-white/10 pt-3">
                        <span class="text-slate-400">Purpose</span>
                        <p class="font-medium text-white mt-1">{{ $loan->purpose }}</p>
                    </div>
                @endif
                @if($loan->notes)
                    <div>
                        <span class="text-slate-400">Notes</span>
                        <p class="font-medium text-white mt-1">{{ $loan->notes }}</p>
                    </div>
                @endif
                @if($loan->submitted_at)
                    <div class="flex items-center justify-between border-t border-white/10 pt-3">
                        <span class="text-slate-400">Submitted</span>
                        <span class="font-medium text-white">{{ $loan->submitted_at->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($loan->creator)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Created by</span>
                        <span class="font-medium text-white">{{ $loan->creator->full_name ?? $loan->creator->email }}</span>
                    </div>
                @endif
                @if($loan->approver)
                    <div class="flex items-center justify-between border-t border-white/10 pt-3">
                        <span class="text-slate-400">Approved by</span>
                        <span class="font-medium text-white">{{ $loan->approver->full_name ?? '—' }}</span>
                    </div>
                @endif
                @if($loan->approved_at)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Approved at</span>
                        <span class="font-medium text-white">{{ $loan->approved_at->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($loan->rejection_reason)
                    <div class="border-t border-white/10 pt-3">
                        <span class="text-slate-400">Rejection reason</span>
                        <p class="font-medium text-rose-200 mt-1">{{ $loan->rejection_reason }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            @include('admin.hr.employee-loans.partials.financial-summary', ['loan' => $loan])
        </div>

        <div class="md:col-span-2 grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Employee information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Name</span>
                        @if($employee)
                            <a href="{{ route('admin.hr.employees.show', $employee) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                                {{ $employee->full_name }}
                            </a>
                        @else
                            <span class="font-medium text-white">—</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Employee #</span>
                        <span class="font-medium text-white">{{ $employee?->employee_number ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Department</span>
                        <span class="font-medium text-white">{{ $employee?->hrDepartment?->name ?? $employee?->department ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Position</span>
                        <span class="font-medium text-white">{{ $employee?->position?->name ?? '—' }}</span>
                    </div>
                    @if($employee?->email)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Email</span>
                            <span class="font-medium text-white">{{ $employee->email }}</span>
                        </div>
                    @endif
                    @if($employee?->phone)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Phone</span>
                            <span class="font-medium text-white">{{ $employee->phone }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Disbursement</h2>
                <div class="space-y-3 text-sm">
                    @php $payoutSummary = $loan->payoutDestinationSummary(! $canViewPayoutNumbers); @endphp
                    @if($payoutSummary)
                        <div>
                            <span class="text-slate-400">Payout destination</span>
                            <p class="font-medium text-white mt-1">{{ $payoutSummary }}</p>
                        </div>
                    @else
                        <p class="text-slate-500">No payout destination recorded.</p>
                    @endif
                    <div class="flex items-center justify-between border-t border-white/10 pt-3">
                        <span class="text-slate-400">Disbursement status</span>
                        <span @class([
                            'inline-block rounded-full px-2 py-1 text-xs capitalize' => ! $disbursementUsesStatusPill,
                            $disbursementStatusColor,
                        ])>{{ str_replace('_', ' ', $loan->disbursement_status) }}</span>
                    </div>
                    @if($loan->disbursed_at)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Disbursed at</span>
                            <span class="font-medium text-white">{{ $loan->disbursed_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    @if($loan->disbursement_reference)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Reference</span>
                            <span class="font-medium text-white">{{ $loan->disbursement_reference }}</span>
                        </div>
                    @endif
                    @if($loan->disburser)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Disbursed by</span>
                            <span class="font-medium text-white">{{ $loan->disburser->full_name ?? '—' }}</span>
                        </div>
                    @endif
                    @if(($disbursementAttempts ?? collect())->isNotEmpty())
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-3 text-sm space-y-2 border-t border-white/10 pt-3 mt-2">
                            <p class="text-slate-300 font-medium">Gateway attempts</p>
                            @foreach($disbursementAttempts as $attempt)
                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-white/5 pt-2 first:border-t-0 first:pt-0">
                                    <span class="text-slate-400">{{ $attempt->provider_reference ?? $attempt->internal_reference }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs bg-white/10 text-slate-200 capitalize">{{ $attempt->status->value ?? $attempt->status }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('disburse', $loan)
        @if($loan->status === 'approved' && in_array($loan->disbursement_status, ['pending', 'failed'], true))
            <div id="employeeLoanDisbursementModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-white mb-2">Record treasury disbursement</h2>
                    <p class="text-sm text-slate-300 mb-4">Debit a treasury bank or wallet and mark this loan active. Use “already paid externally” if the employee was paid outside cGrate.</p>

                    <form method="POST" action="{{ route('admin.hr.employee-loans.disburse', $loan) }}" class="space-y-4">
                        @csrf
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm">
                            <p class="text-slate-400 mb-1">Payout destination</p>
                            <p class="text-white font-medium">{{ $loan->payoutDestinationSummary(! ($canViewFinancials ?? false)) ?? '—' }}</p>
                        </div>

                        <label class="flex items-start gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
                            <input type="checkbox" name="external_already_paid" value="1" class="mt-1" @checked(old('external_already_paid'))>
                            <span>Payout was already made externally — only record the treasury deduction and activate the loan.</span>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-300">Disbursement date <span class="text-rose-400">*</span></label>
                                <input type="date" name="disbursement_date" required value="{{ old('disbursement_date', now()->toDateString()) }}"
                                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-300">Reference <span class="text-rose-400">*</span></label>
                                <input type="text" name="reference_number" required value="{{ old('reference_number') }}"
                                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5" placeholder="Bank / internal reference">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-300">Paid from — type <span class="text-rose-400">*</span></label>
                                <select name="source_type" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                                    <option value="bank" @selected(old('source_type', 'bank') === 'bank')>Bank</option>
                                    <option value="wallet" @selected(old('source_type') === 'wallet')>Wallet</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-300">Paid from — account <span class="text-rose-400">*</span></label>
                                <select name="source_id" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" @selected((string) old('source_id') === (string) $bank->id)>Bank: {{ $bank->name }} ({{ number_format((float) $bank->current_balance, 2) }})</option>
                                    @endforeach
                                    @foreach($wallets as $wallet)
                                        <option value="{{ $wallet->id }}" @selected((string) old('source_id') === (string) $wallet->id)>Wallet: {{ $wallet->name }} ({{ number_format((float) $wallet->current_balance, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-slate-300">Description</label>
                                <textarea name="description" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" onclick="closeEmployeeLoanDisbursementModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2.5 text-sm text-white hover:bg-white/10">Cancel</button>
                            <button type="submit" class="flex-1 btn-primary rounded-2xl px-4 py-2.5 text-sm font-semibold">
                                Record K {{ number_format((float) $loan->principal_amount, 2) }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @push('scripts')
            <script>
                function openEmployeeLoanDisbursementModal() {
                    document.getElementById('employeeLoanDisbursementModal')?.classList.remove('hidden');
                }
                function closeEmployeeLoanDisbursementModal() {
                    document.getElementById('employeeLoanDisbursementModal')?.classList.add('hidden');
                }
                document.getElementById('employeeLoanDisbursementModal')?.addEventListener('click', function (e) {
                    if (e.target === this) closeEmployeeLoanDisbursementModal();
                });
                @if($errors->has('source_id') || $errors->has('reference_number'))
                document.addEventListener('DOMContentLoaded', openEmployeeLoanDisbursementModal);
                @endif
            </script>
            @endpush
        @endif
    @endcan

    @can('repay', $loan)
        @if($loan->isActive())
            <div id="repayment-panel" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg scroll-mt-8">
                <h2 class="text-xl font-semibold text-white mb-4">Record repayment</h2>
                <form method="POST" action="{{ route('admin.hr.employee-loans.repay', $loan) }}" class="grid md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-400 mb-1 block">Amount</label>
                        <input type="number" step="0.01" name="amount" placeholder="0.00" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 mb-1 block">Effective date</label>
                        <input type="date" name="effective_date" value="{{ now()->toDateString() }}" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 mb-1 block">Received via</label>
                        <select name="received_via_type" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            <option value="">Optional</option>
                            <option value="bank">Bank</option>
                            <option value="wallet">Wallet</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 mb-1 block">Treasury account</label>
                        <select name="received_via_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit" class="btn-primary rounded-2xl px-5 py-2.5 text-sm font-semibold">Save repayment</button>
                    </div>
                </form>
            </div>
        @endif
    @endcan

    @can('settle', $loan)
        @if($settlementQuote && $loan->isActive())
            <div class="rounded-3xl border border-purple-500/20 bg-purple-500/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-2">Early settlement</h2>
                <p class="text-sm text-slate-300 mb-4">Quote: <span class="font-semibold text-white">K {{ number_format($settlementQuote['settlement_total'], 2) }}</span></p>
                <form method="POST" action="{{ route('admin.hr.employee-loans.settle', $loan) }}" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-400 mb-1 block">Effective date</label>
                        <input type="date" name="effective_date" value="{{ now()->toDateString() }}" class="rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    </div>
                    <button type="submit" class="rounded-2xl border border-purple-400/40 bg-gradient-to-r from-purple-500 to-purple-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-purple-500/30 hover:from-purple-600 hover:to-purple-800 transition">
                        Settle loan
                    </button>
                </form>
            </div>
        @endif
    @endcan

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <h2 class="text-xl font-semibold text-white mb-4">Repayment schedule</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full w-full text-sm text-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-center text-sm font-semibold uppercase tracking-[0.2em] border-b border-slate-300">
                        <th class="px-4 py-3 text-left text-slate-800">#</th>
                        <th class="px-4 py-3 text-left text-slate-800">Due date</th>
                        <th class="px-4 py-3 text-right text-slate-800">Expected</th>
                        <th class="px-4 py-3 text-right text-slate-800">Principal</th>
                        <th class="px-4 py-3 text-right text-slate-800">Interest</th>
                        <th class="px-4 py-3 text-right text-slate-800">Paid</th>
                        <th class="px-4 py-3 text-right text-slate-800">Remaining</th>
                        <th class="px-4 py-3 text-left text-slate-800">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loan->paymentSchedules as $row)
                        <tr class="border-t border-white/5">
                            <td class="px-4 py-3 text-white">{{ $row->period_number }}</td>
                            <td class="px-4 py-3">{{ $row->due_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-white">{{ number_format((float) $row->expected_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-green-400">{{ number_format((float) $row->principal_component, 2) }}</td>
                            <td class="px-4 py-3 text-right text-amber-400">{{ number_format((float) $row->interest_component, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row->amount_paid, 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-white">{{ number_format((float) $row->remaining_amount, 2) }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $row->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                Schedule is generated when the loan is approved.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <h2 class="text-xl font-semibold text-white mb-4">Repayment history</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full w-full text-sm text-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-center text-sm font-semibold uppercase tracking-[0.2em] border-b border-slate-300">
                        <th class="px-4 py-3 text-left text-slate-800">Date</th>
                        <th class="px-4 py-3 text-right text-slate-800">Amount</th>
                        <th class="px-4 py-3 text-right text-slate-800">Principal</th>
                        <th class="px-4 py-3 text-right text-slate-800">Interest</th>
                        <th class="px-4 py-3 text-left text-slate-800">Reference</th>
                        <th class="px-4 py-3 text-left text-slate-800">Processed by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loan->repayments->sortByDesc('effective_date') as $rep)
                        <tr class="border-t border-white/5">
                            <td class="px-4 py-3 text-slate-400">
                                {{ $rep->effective_date->format('d M Y') }}
                                <div class="text-xs text-slate-500">{{ $rep->created_at?->format('g:i A') }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-white">K {{ number_format((float) $rep->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-green-400">K {{ number_format((float) $rep->principal_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-amber-400">K {{ number_format((float) $rep->interest_amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $rep->reference ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $rep->processor?->full_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No repayments recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canApprovePending)
    @include('admin.hr.employee-loans.partials.approval-modals', [
        'loan' => $loan,
        'approvalAutoDisbursementPreview' => $approvalAutoDisbursementPreview ?? null,
        'approvalSchedulePreview' => $approvalSchedulePreview ?? null,
    ])
@endif
@endsection
