@php
    $statement = $loanStatement ?? $loan->getSimpleStatementSummary();
@endphp

<div id="loanStatementModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
     onclick="if (event.target === this) closeLoanStatementModal()"
     role="dialog"
     aria-modal="true"
     aria-labelledby="loanStatementModalTitle">
    <div class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-[var(--brand-border)] bg-[var(--color-surface)] p-6 shadow-2xl"
         onclick="event.stopPropagation()">
        <div class="mb-5 flex items-start justify-between gap-3 border-b border-[var(--brand-border)] pb-4">
            <div>
                <h2 id="loanStatementModalTitle" class="text-xl font-semibold text-[var(--color-primary)]">Loan Statement</h2>
                <p class="mt-1 text-sm text-[var(--color-muted)]">{{ $loan->loan_number }}</p>
            </div>
            <button type="button"
                    onclick="closeLoanStatementModal()"
                    class="rounded-xl border border-[var(--brand-border)] px-3 py-1.5 text-sm font-semibold text-[var(--color-primary)] hover:bg-[var(--color-surface-alt)] transition"
                    aria-label="Close loan statement">
                Close
            </button>
        </div>

        <dl class="space-y-3 text-sm">
            <div class="flex items-center justify-between rounded-xl bg-[var(--color-surface-alt)] px-4 py-3">
                <dt class="text-[var(--color-muted)]">Principal</dt>
                <dd class="font-semibold text-[var(--color-primary)]">ZMW {{ number_format($statement['principal'], 2) }}</dd>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-[var(--color-surface-alt)] px-4 py-3">
                <dt class="text-[var(--color-muted)]">Processing fee</dt>
                <dd class="font-semibold text-[var(--color-primary)]">ZMW {{ number_format($statement['processing_fee'], 2) }}</dd>
            </div>

            <div class="rounded-xl border border-[var(--brand-border)] px-4 py-3 space-y-2">
                <div class="flex items-center justify-between">
                    <dt class="font-medium text-[var(--color-primary)]">Interest</dt>
                    <dd class="text-xs uppercase tracking-wide text-[var(--color-muted)]">{{ $statement['interest_mode_label'] }}</dd>
                </div>

                @if ($statement['is_daily_accrual'])
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[var(--color-muted)]">Accrued to date</span>
                        <span class="font-semibold text-emerald-600">ZMW {{ number_format($statement['interest_accrued_to_date'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[var(--color-muted)]">Full term (if held to end)</span>
                        <span class="font-semibold text-[var(--color-primary)]">ZMW {{ number_format($statement['interest_full_term'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm border-t border-[var(--brand-border)] pt-2">
                        <span class="text-[var(--color-muted)]">Still to accrue</span>
                        <span class="font-semibold text-amber-600">ZMW {{ number_format($statement['interest_remaining'], 2) }}</span>
                    </div>
                    <p class="text-xs text-[var(--color-muted)] pt-1">Daily accrual loans earn interest over time. Only accrued interest is booked on the ledger until earned.</p>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[var(--color-muted)]">Interest (booked upfront)</span>
                        <span class="font-semibold text-[var(--color-primary)]">ZMW {{ number_format($statement['interest_full_term'], 2) }}</span>
                    </div>
                    <p class="text-xs text-[var(--color-muted)] pt-1">Full interest was included in the loan total at disbursement.</p>
                @endif
            </div>

            <div class="flex items-center justify-between rounded-xl bg-[var(--color-surface-alt)] px-4 py-3">
                <dt class="text-[var(--color-muted)]">Booked at origination</dt>
                <dd class="font-semibold text-[var(--color-primary)]">ZMW {{ number_format($statement['booked_at_origination'], 2) }}</dd>
            </div>
            <div class="flex items-center justify-between rounded-xl border-2 border-[var(--brand-border)] px-4 py-3">
                <dt class="font-medium text-[var(--color-primary)]">Total if held to term</dt>
                <dd class="text-lg font-bold text-[var(--color-primary)]">ZMW {{ number_format($statement['total_if_held_to_term'], 2) }}</dd>
            </div>

            @if (! empty($loanLedger))
                <div class="border-t border-[var(--brand-border)] pt-3 space-y-2">
                    <p class="text-xs uppercase tracking-wide text-[var(--color-muted)]">Current position</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[var(--color-muted)]">Amount paid</span>
                        <span class="font-semibold text-emerald-600">ZMW {{ number_format($loanLedger['net_paid'] ?? $loan->amount_paid, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[var(--color-muted)]">Outstanding balance</span>
                        <span class="font-semibold text-[var(--color-primary)]">ZMW {{ number_format($loanLedger['outstanding'] ?? $loan->outstanding_balance, 2) }}</span>
                    </div>
                </div>
            @endif
        </dl>
    </div>
</div>

@push('scripts')
<script>
    function openLoanStatementModal() {
        const modal = document.getElementById('loanStatementModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeLoanStatementModal() {
        const modal = document.getElementById('loanStatementModal');
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeLoanStatementModal();
        }
    });
</script>
@endpush
