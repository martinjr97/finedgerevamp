@php
    $exposure = $customer->getLoanExposureSummary();
@endphp
<div class="rounded-2xl border border-white/10 bg-black/20 p-4 text-sm">
    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-cyan-400">Loan exposure</p>
    <div class="grid gap-2 sm:grid-cols-2">
        <div class="flex items-center justify-between gap-2">
            <span class="text-slate-400">Maximum exposure</span>
            <span class="font-medium text-white">ZMW {{ number_format($exposure['maximum_loan_take'], 2) }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
            <span class="text-slate-400">Outstanding exposure</span>
            <span class="font-medium text-rose-300">ZMW {{ number_format($exposure['outstanding_balance'], 2) }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
            <span class="text-slate-400">Available exposure</span>
            <span class="font-medium text-emerald-400">ZMW {{ number_format($exposure['available_loan_amount'], 2) }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
            <span class="text-slate-400">Multiple active loans</span>
            <span class="font-medium {{ $exposure['allow_multiple_loans'] ? 'text-emerald-300' : 'text-amber-300' }}">
                {{ $exposure['allow_multiple_loans'] ? 'Allowed' : 'Not allowed' }}
            </span>
        </div>
    </div>
    @if(!empty($exposure['loan_eligibility_blocking_message']))
        <p class="mt-3 text-xs text-amber-300">{{ $exposure['loan_eligibility_blocking_message'] }}</p>
    @endif
</div>
