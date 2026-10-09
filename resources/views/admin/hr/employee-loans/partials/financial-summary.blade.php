@php
    $draftPricing = $loan->metadata['draft_pricing'] ?? $loan->metadata['pricing_quote'] ?? [];
    $installmentAmount = $loan->paymentSchedules->first()?->expected_amount
        ?? ($draftPricing['installment_amount'] ?? null);
    $installmentCount = $loan->paymentSchedules->isNotEmpty()
        ? $loan->paymentSchedules->count()
        : (int) ($draftPricing['installment_count'] ?? 0);
    $scheduleTotal = $loan->paymentSchedules->sum(fn ($row) => (float) $row->expected_amount);
    $interestBehavior = $loan->interest_behavior
        ? ucfirst(str_replace('_', ' ', (string) $loan->interest_behavior))
        : null;
@endphp

<div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
    <h2 class="text-xl font-semibold text-white">Financial Summary</h2>

    @if ($loan->status === 'pending_approval')
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            Amounts below reflect the quoted terms at submission. Approving will freeze pricing and generate the repayment schedule.
        </div>
    @endif

    @if ($loan->status === 'settled')
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 space-y-1">
            <p><strong>Settled</strong>@if($loan->loan_settled_date) on {{ $loan->loan_settled_date->format('d M Y') }}@endif.</p>
            @if ($loan->settlement_amount)
                <p>Settlement payoff: K {{ number_format((float) $loan->settlement_amount, 2) }}</p>
            @endif
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 text-sm">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Principal</span>
                <span class="font-medium text-white">K {{ number_format((float) $loan->principal_amount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Processing fee</span>
                <span class="font-medium text-white">
                    K {{ number_format((float) ($loan->processing_fee ?? 0), 2) }}
                    @if ($loan->processing_fee_percentage)
                        <span class="text-slate-400 text-xs">({{ number_format((float) $loan->processing_fee_percentage, 2) }}%)</span>
                    @endif
                </span>
            </div>
            @if ($interestBehavior)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Interest behavior</span>
                    <span class="font-medium text-white">{{ $interestBehavior }}</span>
                </div>
            @endif
            @if ($loan->quoted_term_rate !== null)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Quoted term rate</span>
                    <span class="font-medium text-white">{{ number_format((float) $loan->quoted_term_rate, 2) }}%</span>
                </div>
            @endif
            @if ((float) ($loan->arrear_rate ?? 0) > 0)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Arrear rate</span>
                    <span class="font-medium text-white">{{ number_format((float) $loan->arrear_rate, 2) }}%</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Quoted interest</span>
                <span class="font-medium text-white">K {{ number_format((float) ($loan->interest_accrued ?? 0), 2) }}</span>
            </div>
            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                <span class="text-slate-300 font-medium">Total repayable</span>
                <span class="font-semibold text-white">K {{ number_format((float) $loan->total_amount, 2) }}</span>
            </div>
        </div>

        <div class="space-y-3 md:border-l md:border-white/10 md:pl-4">
            <div class="flex items-center justify-between">
                <span class="text-slate-400 font-semibold">Outstanding balance</span>
                <span class="font-bold text-lg text-cyan-300">K {{ number_format((float) $loan->outstanding_balance, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Amount paid</span>
                <span class="font-medium text-emerald-300">K {{ number_format((float) $loan->amount_paid, 2) }}</span>
            </div>
            @if ($installmentAmount !== null)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Installment (est.)</span>
                    <span class="font-medium text-white">
                        K {{ number_format((float) $installmentAmount, 2) }}
                        @if ($installmentCount > 0)
                            <span class="text-slate-400 text-xs">× {{ $installmentCount }}</span>
                        @endif
                    </span>
                </div>
            @endif
            @if ($loan->paymentSchedules->isNotEmpty())
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Schedule total</span>
                    <span class="text-slate-400">K {{ number_format((float) $scheduleTotal, 2) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Repayment frequency</span>
                <span class="font-medium text-white capitalize">{{ $loan->repayment_frequency ?? '—' }}</span>
            </div>
        </div>
    </div>
</div>
