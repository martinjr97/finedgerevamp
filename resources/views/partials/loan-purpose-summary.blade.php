@if (! empty($loanPurpose))
    <div>
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Loan Purpose</p>
        <p class="text-base font-semibold text-white">{{ $loanPurpose->name }}</p>
    </div>
@endif
