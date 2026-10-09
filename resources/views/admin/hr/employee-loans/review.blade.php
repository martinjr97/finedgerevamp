@extends('layouts.admin')

@section('title', 'Review Employee Loan | HR')

@section('content')
@php
    $pricing = $schedulePreview['pricing'] ?? [];
    $isExistingDraft = isset($loan);
    $isEditingSession = isset($editingLoan) && $editingLoan && ! $isExistingDraft;
    $principal = $isExistingDraft ? (float) $loan->principal_amount : (float) ($payload['principal_amount'] ?? 0);
    $tenure = $isExistingDraft ? (int) $loan->tenure_months : (int) ($payload['tenure_months'] ?? 0);
    $frequency = $isExistingDraft ? $loan->repayment_frequency : ($payload['repayment_frequency'] ?? 'monthly');
    $purpose = $isExistingDraft ? $loan->purpose : ($payload['purpose'] ?? '—');
    $notes = $isExistingDraft ? $loan->notes : ($payload['notes'] ?? null);
    $termRate = $loanRateLabel ?? $pricing['quoted_term_rate'] ?? '—';
    $procFeePct = $processingFeePercentage ?? null;
    $interest = (float) ($pricing['interest'] ?? ($isExistingDraft ? $loan->interest_accrued : 0));
    $procFee = (float) ($pricing['processing_fee'] ?? ($isExistingDraft ? $loan->processing_fee : 0));
    $totalRepayable = (float) ($pricing['total_repayable'] ?? ($isExistingDraft ? $loan->total_amount : 0));
@endphp
<div class="space-y-8">
    @include('partials.admin.page-header', [
        'title' => 'Review employee loan',
        'description' => $isExistingDraft
            ? 'Draft '.$loan->loan_number.' — confirm before submitting for approval.'
            : ($isEditingSession
                ? 'Updating draft '.$editingLoan->loan_number.' — confirm the breakdown below.'
                : 'No loan has been saved yet. Confirm the breakdown below to create the record.'),
        'buttons' => [[
            'action' => 'secondary',
            'text' => $isExistingDraft ? 'Back to loan' : 'Back to form',
            'href' => $isExistingDraft ? route('admin.hr.employee-loans.show', $loan) : route('admin.hr.employee-loans.create'),
        ]],
    ])

    @if(! $isExistingDraft && ! $isEditingSession)
        <div class="rounded-2xl border border-cyan-500/30 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100">
            This is a final review only — nothing is stored until you confirm. Use <strong>Back to form</strong> to change inputs without creating a draft.
        </div>
    @endif
    @if($isEditingSession)
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            Changes are not saved until you confirm. <a href="{{ route('admin.hr.employee-loans.edit', $editingLoan) }}" class="underline text-white">Back to edit</a>
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-6">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Employee</h2>
            <dl class="grid gap-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Name</dt><dd class="text-white font-medium">{{ $employee?->full_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Employee #</dt><dd class="text-white">{{ $employee?->employee_number ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Department</dt><dd class="text-white">{{ $employee?->hrDepartment?->name ?? $employee?->department ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Purpose</dt><dd class="text-white">{{ $purpose }}</dd></div>
                @if($notes)
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Notes</dt><dd class="text-white text-right max-w-[60%]">{{ $notes }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Pricing breakdown</h2>
            <dl class="grid gap-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Principal amount</dt><dd class="text-white font-semibold text-base">K {{ number_format($principal, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Term interest rate</dt><dd class="text-white">{{ $termRate }}%</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Interest amount</dt><dd class="text-white">K {{ number_format($interest, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Processing fee @if($procFeePct !== null)({{ $procFeePct }}%)@endif</dt><dd class="text-white">K {{ number_format($procFee, 2) }}</dd></div>
                @if(isset($arrearRate) && (float) $arrearRate > 0)
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Arrear rate (if overdue)</dt><dd class="text-white">{{ $arrearRate }}%</dd></div>
                @endif
                <div class="flex justify-between gap-4 border-t border-white/10 pt-2 mt-1"><dt class="text-slate-200 font-medium">Total repayable</dt><dd class="text-cyan-200 font-bold text-lg">K {{ number_format($totalRepayable, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Tenure</dt><dd class="text-white">{{ $tenure }} months</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Repayment frequency</dt><dd class="text-white capitalize">{{ $frequency }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Expected installment</dt><dd class="text-white font-medium">K {{ number_format((float) ($schedulePreview['installment_amount'] ?? 0), 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-400">Number of installments</dt><dd class="text-white">{{ $pricing['installment_count'] ?? count($schedulePreview['repayment_schedule'] ?? []) }}</dd></div>
            </dl>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 space-y-4 md:col-span-2">
            <h2 class="text-lg font-semibold text-white">Key dates</h2>
            <dl class="grid sm:grid-cols-3 gap-4 text-sm">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <dt class="text-slate-400 text-xs uppercase tracking-wide">Application / start</dt>
                    <dd class="mt-1 text-lg font-semibold text-white">{{ \Carbon\Carbon::parse($schedulePreview['loan_start_date'])->format('d M Y') }}</dd>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <dt class="text-slate-400 text-xs uppercase tracking-wide">First repayment</dt>
                    <dd class="mt-1 text-lg font-semibold text-white">
                        @if($schedulePreview['first_payment_date'])
                            {{ \Carbon\Carbon::parse($schedulePreview['first_payment_date'])->format('d M Y') }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <dt class="text-slate-400 text-xs uppercase tracking-wide">Final installment (expected end)</dt>
                    <dd class="mt-1 text-lg font-semibold text-white">
                        @if($schedulePreview['loan_end_date'])
                            {{ \Carbon\Carbon::parse($schedulePreview['loan_end_date'])->format('d M Y') }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        @if($payoutSummary ?? null)
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 md:col-span-2">
                <h2 class="text-lg font-semibold text-white mb-2">Payment details (disbursement)</h2>
                <p class="text-sm text-slate-300">{{ $payoutSummary }}</p>
            </div>
        @endif
    </div>

    @include('partials.admin.repayment-schedule-preview', [
        'repaymentSchedule' => $schedulePreview['repayment_schedule'] ?? [],
        'showBreakdown' => true,
    ])

    <div class="flex flex-wrap gap-3">
        @if($isExistingDraft)
            @can('update', $loan)
                <form method="POST" action="{{ route('admin.hr.employee-loans.submit', $loan) }}">
                    @csrf
                    <button type="submit" class="rounded-2xl bg-emerald-600 px-6 py-3 font-semibold text-white shadow-lg hover:bg-emerald-500 transition">
                        Confirm and submit for approval
                    </button>
                </form>
            @endcan
            @can('delete', $loan)
                <form method="POST" action="{{ route('admin.hr.employee-loans.destroy', $loan) }}" onsubmit="return confirm('Delete this draft permanently?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-2xl border border-rose-500/50 bg-rose-500/10 px-6 py-3 font-semibold text-rose-200 hover:bg-rose-500/20 transition">
                        Delete draft
                    </button>
                </form>
            @endcan
        @else
            @if($isEditingSession)
                @can('update', $editingLoan)
                    <form method="POST" action="{{ route('admin.hr.employee-loans.confirm') }}">
                        @csrf
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-6 py-3 font-semibold text-white shadow-lg hover:bg-emerald-500 transition">
                            Confirm and submit for approval
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.hr.employee-loans.confirm') }}">
                        @csrf
                        <input type="hidden" name="save_as_draft" value="1">
                        <button type="submit" class="rounded-2xl border border-white/20 px-6 py-3 text-slate-200 hover:bg-white/5 transition">
                            Save draft changes
                        </button>
                    </form>
                    <a href="{{ route('admin.hr.employee-loans.edit', $editingLoan) }}" class="rounded-2xl border border-white/10 px-6 py-3 text-slate-400 hover:text-slate-200 transition inline-block">
                        Back to edit
                    </a>
                @endcan
            @else
                @can('create', \App\Models\EmployeeLoan::class)
                    <form method="POST" action="{{ route('admin.hr.employee-loans.confirm') }}">
                        @csrf
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-6 py-3 font-semibold text-white shadow-lg hover:bg-emerald-500 transition">
                            Confirm and submit for approval
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.hr.employee-loans.confirm') }}">
                        @csrf
                        <input type="hidden" name="save_as_draft" value="1">
                        <button type="submit" class="rounded-2xl border border-white/20 px-6 py-3 text-slate-200 hover:bg-white/5 transition">
                            Save as draft only
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.hr.employee-loans.review.cancel') }}">
                        @csrf
                        <button type="submit" class="rounded-2xl border border-white/10 px-6 py-3 text-slate-400 hover:text-slate-200 transition">
                            Cancel review
                        </button>
                    </form>
                @endcan
            @endif
        @endif
    </div>
</div>
@endsection
