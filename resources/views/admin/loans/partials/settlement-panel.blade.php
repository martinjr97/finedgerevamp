@can('loans.disburse')
    @if (! $loan->isSettled() && (float) $loan->outstanding_balance > 0)
        <div
            id="earlySettlementRoot"
            x-data="{
                open: false,
                settlementDate: @js(now()->toDateString()),
                quote: null,
                loading: false,
                error: null,
                quoteUrl: @js(route('admin.loans.settlement.quote', $loan)),
                applyUrl: @js(route('admin.loans.settlement.apply', $loan)),
                async openModal() {
                    this.open = true;
                    if (!this.quote) {
                        await this.fetchQuote();
                    }
                },
                closeModal() {
                    this.open = false;
                },
                async fetchQuote() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const res = await fetch(this.quoteUrl + '?settlement_date=' + encodeURIComponent(this.settlementDate), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'Failed to load quote');
                        this.quote = data;
                    } catch (e) {
                        this.error = e.message;
                        this.quote = null;
                    } finally {
                        this.loading = false;
                    }
                },
                async applySettlement() {
                    if (!this.quote) return;
                    this.loading = true;
                    this.error = null;
                    try {
                        const res = await fetch(this.applyUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                amount: this.quote.payoff_amount,
                                settlement_date: this.settlementDate,
                                channel_id: @js($loan->channel_id),
                                phone_number: @js($loan->disbursement_phone_number),
                                notes: 'Admin settlement via loan show',
                            }),
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'Settlement failed');
                        window.location.reload();
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.loading = false;
                    }
                }
            }"
            @keydown.escape.window="if (open) closeModal()"
            @open-early-settlement.window="openModal()"
        >
            <div
                x-show="open"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModal()"
            >
                <div
                    class="modal-content w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl border border-purple-500/30 bg-slate-900 p-6 shadow-2xl"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Early Settlement</h3>
                            <p class="mt-1 text-sm text-slate-300">
                                <strong>Settlement payoff</strong> is the amount needed to close the loan today
                                (earned interest only for daily accrual loans).
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeModal()"
                            class="rounded-xl border border-white/10 p-2 text-slate-300 hover:bg-white/10 transition"
                            aria-label="Close"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Settlement date</label>
                            <input type="date" x-model="settlementDate" class="rounded-xl bg-white/10 border border-white/10 text-white px-3 py-2 text-sm">
                        </div>
                        <button type="button" @click="fetchQuote()" :disabled="loading" class="rounded-xl bg-purple-500/20 border border-purple-500/50 px-4 py-2 text-sm font-semibold text-purple-200 hover:bg-purple-500/30 disabled:opacity-50">
                            Refresh quote
                        </button>
                    </div>

                    <template x-if="error">
                        <p class="text-sm text-rose-400 mb-3" x-text="error"></p>
                    </template>

                    <template x-if="loading && !quote">
                        <p class="text-sm text-slate-400">Loading settlement quote…</p>
                    </template>

                    <template x-if="quote">
                        <div>
                            <div class="grid gap-2 text-sm md:grid-cols-2 mb-4">
                                <div class="flex justify-between gap-3"><span class="text-slate-400">Principal remaining</span><span class="text-white font-medium" x-text="'ZMW ' + Number(quote.principal_remaining).toLocaleString(undefined, {minimumFractionDigits: 2})"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-slate-400">Processing fee remaining</span><span class="text-white font-medium" x-text="'ZMW ' + Number(quote.processing_fee_remaining).toLocaleString(undefined, {minimumFractionDigits: 2})"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-slate-400">Earned interest</span><span class="text-white font-medium" x-text="'ZMW ' + Number(quote.interest_earned).toLocaleString(undefined, {minimumFractionDigits: 2})"></span></div>
                                <div class="flex justify-between gap-3"><span class="text-slate-400">Interest paid</span><span class="text-white font-medium" x-text="'ZMW ' + Number(quote.interest_paid || 0).toLocaleString(undefined, {minimumFractionDigits: 2})"></span></div>
                                <div class="flex justify-between gap-3" x-show="quote.unearned_interest_rebate && Number(quote.unearned_interest_rebate) > 0">
                                    <span class="text-slate-400">Rebate (unearned interest)</span>
                                    <span class="text-emerald-300 font-medium" x-text="'ZMW ' + Number(quote.unearned_interest_rebate).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                                </div>
                                <div class="flex justify-between gap-3 md:col-span-2 pt-2 border-t border-white/10">
                                    <span class="text-slate-300 font-semibold">Settlement payoff</span>
                                    <span class="text-lg font-bold text-purple-200" x-text="'ZMW ' + Number(quote.payoff_amount).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                                </div>
                            </div>
                            <div class="flex flex-wrap justify-end gap-3">
                                <button type="button" @click="closeModal()" class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/10 transition">
                                    Cancel
                                </button>
                                <button type="button" @click="applySettlement()" :disabled="loading" class="rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-lg disabled:opacity-50">
                                    Apply settlement payment
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    @endif
@endcan
