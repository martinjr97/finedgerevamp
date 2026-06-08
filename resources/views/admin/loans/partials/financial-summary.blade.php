@php
    $projectedTotal = $loan->getProjectedTotalAmount();
    $projectedInterest = $loan->getProjectedInterest();
    $scheduleTotal = $loan->getScheduleExpectedTotal();
    $showsDisclosure = $loan->showsDailyAccrualDisclosure();
@endphp

<div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
    <h2 class="text-xl font-semibold text-white">Financial Summary</h2>

    @if ($showsDisclosure)
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            This loan uses <strong>daily accrual</strong>. <strong>Projected repayment</strong> includes future expected interest;
            <strong>booked outstanding balance</strong> and <strong>settlement payoff</strong> use earned interest only.
        </div>
    @endif

    @if ($loan->isSettled())
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 space-y-1">
            <p><strong>Settled</strong> on {{ $loan->loan_settled_date?->format('d M Y') ?? $loan->settlement_date?->format('d M Y') }}.</p>
            @if ($loan->settlement_amount)
                <p>Settlement payoff: ZMW {{ number_format($loan->settlement_amount, 2) }}</p>
            @endif
            @if ($loan->rebate_amount && (float) $loan->rebate_amount > 0)
                <p>Interest rebate: ZMW {{ number_format($loan->rebate_amount, 2) }}</p>
            @endif
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 text-sm">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Principal</span>
                <span class="font-medium text-white">ZMW {{ number_format($loan->principal_amount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Processing / Admin Fee</span>
                <span class="font-medium text-white">ZMW {{ number_format($loan->processing_fee, 2) }}
                    @if ($loan->processing_fee_percentage)
                        <span class="text-slate-400 text-xs">({{ number_format($loan->processing_fee_percentage, 2) }}%)</span>
                    @endif
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Interest behavior</span>
                <span class="font-medium text-white">{{ $loan->getInterestBehaviorLabel() }}</span>
            </div>
            @if ($loan->getRateInputModeLabel())
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Rate input mode</span>
                    <span class="font-medium text-white">{{ $loan->getRateInputModeLabel() }}</span>
                </div>
            @endif
            @if ($loan->quoted_term_rate !== null)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Quoted term rate</span>
                    <span class="font-medium text-white">{{ number_format($loan->quoted_term_rate, 2) }}%</span>
                </div>
            @endif
            @if ($loan->daily_rate)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Daily rate (multiplier)</span>
                    <span class="font-medium text-white">{{ number_format((float) $loan->daily_rate * 100, 6) }}%</span>
                </div>
            @endif
            @if ($loan->weekly_rate)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Weekly rate (multiplier)</span>
                    <span class="font-medium text-white">{{ number_format((float) $loan->weekly_rate * 100, 6) }}%</span>
                </div>
            @endif
        </div>

        <div class="space-y-3 md:border-l md:border-white/10 md:pl-4">
            @isset($loanLedger)
                <div class="rounded-xl border border-cyan-500/20 bg-cyan-500/5 px-4 py-3 space-y-2 mb-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Repayment ledger</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">Expected settlement</span>
                        <span class="font-medium text-white">ZMW {{ number_format($loanLedger['expected_settlement'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">Net paid</span>
                        <span class="font-medium text-emerald-300">ZMW {{ number_format($loanLedger['net_paid'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">Outstanding balance</span>
                        <span class="font-semibold text-cyan-300">ZMW {{ number_format($loanLedger['outstanding'], 2) }}</span>
                    </div>
                    @if ($loanLedger['suspense'] > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Suspense (over settlement)</span>
                            <span class="font-medium text-amber-300">ZMW {{ number_format($loanLedger['suspense'], 2) }}</span>
                        </div>
                    @endif
                </div>
            @endisset
            <div class="flex items-center justify-between pt-2 border-t border-white/10 md:border-t-0 md:pt-0">
                <span class="text-slate-400 font-semibold">Booked outstanding balance</span>
                <span class="font-bold text-lg text-cyan-300">ZMW {{ number_format($loan->outstanding_balance, 2) }}</span>
            </div>
            @if ($showsDisclosure || abs($projectedTotal - (float) $loan->outstanding_balance) > 0.01)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Projected repayment total</span>
                    <span class="font-medium text-white">ZMW {{ number_format($projectedTotal, 2) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Earned interest</span>
                <span class="font-medium text-white">ZMW {{ number_format($loan->getEarnedInterest(), 2) }}</span>
            </div>
            @if ($showsDisclosure || $projectedInterest > (float) $loan->interest_accrued + 0.01)
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Projected interest</span>
                    <span class="font-medium text-slate-300">ZMW {{ number_format($projectedInterest, 2) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Net paid (stored)</span>
                <span class="font-medium text-emerald-300">ZMW {{ number_format($loan->amount_paid, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-400">Next installment (est.)</span>
                <span class="font-medium text-white">ZMW {{ number_format($loan->getMonthlyPayment(), 2) }}</span>
            </div>
            @if ($loan->paymentSchedules->isNotEmpty())
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Schedule total (installments)</span>
                    <span class="text-slate-400">ZMW {{ number_format($scheduleTotal, 2) }}
                        @if ($loan->scheduleUsesProjectedInterest())
                            <span class="text-amber-400/80">· projected</span>
                        @endif
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
