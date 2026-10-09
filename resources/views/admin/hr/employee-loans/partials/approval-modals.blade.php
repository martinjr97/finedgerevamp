@props([
    'loan',
    'approvalAutoDisbursementPreview' => null,
    'approvalSchedulePreview' => null,
])

@php
    $employee = $loan->employee;
    $payoutSummary = $loan->payoutDestinationSummary(! auth('admin')->user()?->can('hr.employee-loans.financials'));
    $rateProduct = trim(($loan->loanRate?->loanRateType?->name ?? 'Employee loan').' · '.($loan->tenure_months ?? '—').' mo');

    $schedule = is_array($approvalSchedulePreview) ? $approvalSchedulePreview : [];
    $pricing = $schedule['pricing'] ?? [];
    $installmentAmount = $schedule['installment_amount'] ?? ($pricing['installment_amount'] ?? null);
    $installmentCount = count($schedule['repayment_schedule'] ?? []);
    $expectedEndRaw = $schedule['loan_end_date'] ?? null;
    $expectedEndLabel = $expectedEndRaw
        ? \Carbon\Carbon::parse($expectedEndRaw)->format('d M Y')
        : '—';
    $firstPaymentRaw = $schedule['first_payment_date'] ?? $loan->first_payment_date?->toDateString();
    $firstPaymentLabel = $firstPaymentRaw
        ? \Carbon\Carbon::parse($firstPaymentRaw)->format('d M Y')
        : '—';
    $loanStartRaw = $schedule['loan_start_date'] ?? $loan->application_date?->toDateString();
    $loanStartLabel = $loanStartRaw
        ? \Carbon\Carbon::parse($loanStartRaw)->format('d M Y')
        : now()->format('d M Y');

    $autoDisbursementReady = ($approvalAutoDisbursementPreview?->autoDisbursementReady ?? false) === true;
@endphp

<div id="employeeLoanApproveModal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 backdrop-blur-sm p-4 py-8">
    <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-5xl shadow-2xl my-auto">
        <h3 class="text-xl font-semibold text-white mb-2">Approve employee loan</h3>
        @if($autoDisbursementReady)
            <p class="text-sm text-slate-300 mb-4">
                Confirm approval. Pricing and the repayment schedule will be saved, then
                <span class="text-cyan-200 font-medium">cGrate can disburse automatically</span> unless you choose to skip below.
            </p>
        @else
            <p class="text-sm text-slate-300 mb-4">
                Confirm approval. Pricing and the repayment schedule will be saved.
                <span class="text-amber-200 font-medium">Disbursement will be manual</span> (treasury record or cGrate from this page when you are ready).
            </p>
        @endif

        <div class="grid gap-4 lg:grid-cols-2 mb-4">
            <div class="min-w-0">
                @include('partials.admin.loan-approval-auto-disbursement-notice', [
                    'approvalAutoDisbursementPreview' => $approvalAutoDisbursementPreview,
                ])
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm space-y-3 min-w-0">
                <p class="text-slate-300 font-medium">Payout destination</p>
                @if($payoutSummary)
                    <p class="text-white font-medium">{{ $payoutSummary }}</p>
                @else
                    <p class="text-slate-500">No payout destination on file — configure before disbursing.</p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm space-y-4 mb-6">
            <p class="text-slate-300 font-medium">Loan terms (stored on approval)</p>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Loan number</span>
                        <span class="font-medium text-white">{{ $loan->loan_number }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Employee</span>
                        <span class="font-medium text-white text-right">{{ $employee?->full_name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Product / tenure</span>
                        <span class="font-medium text-white text-right">{{ $rateProduct }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Principal</span>
                        <span class="font-medium text-white">K {{ number_format((float) $loan->principal_amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">Quoted term rate</span>
                        <span class="font-medium text-white">
                            @if($loan->quoted_term_rate !== null)
                                {{ number_format((float) $loan->quoted_term_rate, 2) }}%
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-white/10 pt-2">
                        <span class="text-slate-300 font-medium">Total repayable</span>
                        <span class="font-bold text-cyan-300">K {{ number_format((float) $loan->total_amount, 2) }}</span>
                    </div>
                    @if($installmentAmount !== null && $installmentCount > 0)
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400">Installments</span>
                            <span class="font-medium text-white">
                                K {{ number_format((float) $installmentAmount, 2) }} × {{ $installmentCount }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="space-y-2 rounded-xl border border-cyan-500/20 bg-cyan-500/5 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Schedule preview</p>
                    <p class="text-xs text-slate-400">Same calculation used when you confirm — final dates are saved with the generated schedule.</p>
                    <div class="flex items-center justify-between gap-4 pt-1">
                        <span class="text-slate-400">Loan start</span>
                        <span class="font-medium text-white">{{ $loanStartLabel }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-slate-400">First repayment</span>
                        <span class="font-medium text-white">{{ $firstPaymentLabel }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-cyan-500/20 pt-2">
                        <span class="text-slate-200 font-medium">Expected end</span>
                        <span class="font-bold text-lg text-cyan-200">{{ $expectedEndLabel }}</span>
                    </div>
                    @if($expectedEndRaw === null)
                        <p class="text-xs text-amber-200/90">
                            @if(! $firstPaymentRaw)
                                Add a first repayment date on the loan to compute the final installment date.
                            @else
                                Could not compute expected end — check tenure and repayment frequency.
                            @endif
                        </p>
                    @endif
                    @if($loan->purpose)
                        <div class="border-t border-white/10 pt-2">
                            <span class="text-slate-400">Purpose</span>
                            <p class="font-medium text-white mt-1">{{ $loan->purpose }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <form id="employeeLoanApproveForm" method="POST" action="{{ route('admin.hr.employee-loans.approve', $loan) }}">
            @csrf
            @if($autoDisbursementReady)
                <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300 mb-4 cursor-pointer">
                    <input type="checkbox" name="skip_auto_disbursement" value="1" class="mt-1 rounded border-white/20">
                    <span>
                        <span class="font-medium text-white block">Skip automatic cGrate disbursement</span>
                        Approve and generate the schedule only — record treasury disbursement later (e.g. payout already made outside the gateway).
                    </span>
                </label>
            @endif
            <div class="flex gap-3">
                <button type="button" onclick="closeEmployeeLoanApproveModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2.5 text-sm text-white hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 rounded-2xl border border-emerald-200/40 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:from-emerald-700 hover:to-teal-700 transition">
                    @if($autoDisbursementReady)
                        Confirm approval
                    @else
                        Approve &amp; save schedule
                    @endif
                </button>
            </div>
        </form>
    </div>
</div>

<div id="employeeLoanRejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-lg shadow-2xl">
        <h3 class="text-xl font-semibold text-white mb-2">Reject employee loan</h3>
        <p class="text-sm text-slate-400 mb-4">
            Rejecting <span class="text-white font-medium">{{ $loan->loan_number }}</span>
            for {{ $employee?->full_name ?? 'this employee' }} (K {{ number_format((float) $loan->principal_amount, 2) }} principal).
        </p>
        <form id="employeeLoanRejectForm" method="POST" action="{{ route('admin.hr.employee-loans.reject', $loan) }}">
            @csrf
            <div class="mb-4">
                <label for="employee_loan_rejection_reason" class="block text-sm font-medium text-slate-300 mb-2">Rejection reason <span class="text-rose-400">*</span></label>
                <textarea
                    id="employee_loan_rejection_reason"
                    name="rejection_reason"
                    rows="3"
                    required
                    class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                    placeholder="Explain why this loan cannot be approved…"
                >{{ old('rejection_reason') }}</textarea>
                @error('rejection_reason')
                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeEmployeeLoanRejectModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2.5 text-sm text-white hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/30 hover:bg-rose-700 transition">
                    Confirm rejection
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function showEmployeeLoanApproveModal() {
        document.getElementById('employeeLoanApproveModal')?.classList.remove('hidden');
    }
    function closeEmployeeLoanApproveModal() {
        document.getElementById('employeeLoanApproveModal')?.classList.add('hidden');
    }
    function showEmployeeLoanRejectModal() {
        document.getElementById('employeeLoanRejectModal')?.classList.remove('hidden');
    }
    function closeEmployeeLoanRejectModal() {
        document.getElementById('employeeLoanRejectModal')?.classList.add('hidden');
        document.getElementById('employeeLoanRejectForm')?.reset();
    }
    document.getElementById('employeeLoanApproveModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeEmployeeLoanApproveModal();
    });
    document.getElementById('employeeLoanRejectModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeEmployeeLoanRejectModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeEmployeeLoanApproveModal();
            closeEmployeeLoanRejectModal();
        }
    });
    @if ($errors->has('rejection_reason'))
    document.addEventListener('DOMContentLoaded', function () {
        showEmployeeLoanRejectModal();
    });
    @endif
</script>
@endpush
