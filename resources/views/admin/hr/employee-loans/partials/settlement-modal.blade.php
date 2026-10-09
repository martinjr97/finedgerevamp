@php
    $quote = $settlementQuote ?? [];
    $settlementTotal = (float) ($quote['settlement_total'] ?? 0);
    $channels = $settlementChannels ?? ($repaymentChannels ?? collect());
    $previews = $settlementChannelPreviews ?? ($repaymentChannelPreviews ?? []);
    $defaultPhone = old('settlement_phone', $defaultRepaymentPhone ?? '');
@endphp

<div id="employeeLoanSettlementModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-white mb-1">Early settlement</h2>
        <p class="text-sm text-slate-300 mb-4">
            Pay off the full outstanding balance and close this employee loan.
            Settlement total: <span class="font-semibold text-purple-300">K {{ number_format($settlementTotal, 2) }}</span>
        </p>

        <dl class="mb-5 grid gap-2 sm:grid-cols-2 rounded-2xl border border-purple-500/20 bg-purple-500/5 p-4 text-sm">
            <div class="flex justify-between gap-2"><dt class="text-slate-400">Settlement date</dt><dd class="text-white font-medium">{{ $quote['settlement_date'] ?? now()->toDateString() }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-slate-400">Principal</dt><dd class="text-white font-medium">K {{ number_format($quote['principal_outstanding'] ?? 0, 2) }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-slate-400">Interest (earned)</dt><dd class="text-white font-medium">K {{ number_format($quote['interest_outstanding'] ?? 0, 2) }}</dd></div>
            @if(($quote['unearned_interest_rebate'] ?? 0) > 0)
            <div class="flex justify-between gap-2 sm:col-span-2"><dt class="text-slate-400">Unearned interest rebate</dt><dd class="text-emerald-300 font-medium">K {{ number_format($quote['unearned_interest_rebate'], 2) }}</dd></div>
            @endif
            <div class="flex justify-between gap-2"><dt class="text-slate-400">Fees</dt><dd class="text-white font-medium">K {{ number_format($quote['fees_outstanding'] ?? 0, 2) }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-slate-400">Arrears interest</dt><dd class="text-white font-medium">K {{ number_format($quote['arrears_interest_outstanding'] ?? 0, 2) }}</dd></div>
        </dl>
        @if(! empty($quote['notes']))
            <ul class="mb-4 list-disc pl-5 text-xs text-slate-400 space-y-1">
                @foreach($quote['notes'] as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.hr.employee-loans.settle', $loan) }}" id="employeeLoanSettlementForm" class="space-y-5" onsubmit="return confirm('Settle this loan for K {{ number_format($settlementTotal, 2) }}? This cannot be undone.');">
            @csrf
            <input type="hidden" name="form_context" value="settlement">
            <input type="hidden" name="settlement_total" value="{{ number_format($settlementTotal, 2, '.', '') }}">

            <div>
                <label class="text-sm font-medium text-slate-300">Effective date</label>
                <input type="date" name="effective_date" required value="{{ old('effective_date', now()->toDateString()) }}"
                       class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-300">How is settlement collected?</label>
                <select name="collection_mode" id="el_settlement_collection_mode" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    <option value="gateway" @selected(old('collection_mode', 'gateway') === 'gateway')>Mobile money / gateway (payment prompt)</option>
                    <option value="treasury" @selected(old('collection_mode') === 'treasury')>Already received — payroll / treasury account</option>
                </select>
            </div>

            <div id="el_settlement_gateway_panel" class="rounded-2xl border border-cyan-500/30 bg-cyan-500/5 p-4 space-y-4">
                <p class="text-sm text-cyan-100">Send a collection prompt for the full settlement amount to the employee&apos;s phone.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-300">Repayment channel</label>
                        <select name="channel_id" id="el_settlement_channel_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            @foreach($channels as $channel)
                                @php $preview = $previews[$channel->id] ?? null; @endphp
                                <option value="{{ $channel->id }}" @selected((string) old('channel_id') === (string) $channel->id)>
                                    {{ $channel->name }}@if($preview && ! $preview->ready) (unavailable)@elseif($preview?->ready) (ready)@endif
                                </option>
                            @endforeach
                        </select>
                        @error('channel_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300">Employee mobile</label>
                        <input type="text" name="phone" id="el_settlement_phone" value="{{ $defaultPhone }}"
                               class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    </div>
                </div>
                <p id="el_settlement_gateway_hint" class="text-xs text-slate-400"></p>
            </div>

            <div id="el_settlement_treasury_panel" class="hidden rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-4">
                <p class="text-sm text-emerald-100">Credit the treasury account that holds the settlement funds, then mark the loan settled.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-300">Source</label>
                        <select name="repayment_source" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            <option value="payroll" @selected(old('repayment_source', 'payroll') === 'payroll')>Payroll deduction</option>
                            <option value="bank" @selected(old('repayment_source') === 'bank')>Bank transfer</option>
                            <option value="cash" @selected(old('repayment_source') === 'cash')>Cash</option>
                            <option value="manual" @selected(old('repayment_source') === 'manual')>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300">Account type</label>
                        <select name="received_via_type" id="el_settlement_received_via_type" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            <option value="bank" @selected(old('received_via_type', 'bank') === 'bank')>Bank</option>
                            <option value="wallet" @selected(old('received_via_type') === 'wallet')>Wallet</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300">Treasury account</label>
                        <select name="received_via_id" id="el_settlement_received_via_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" data-type="bank" @selected(old('received_via_type', 'bank') === 'bank' && (string) old('received_via_id') === (string) $bank->id)>
                                    Bank: {{ $bank->name }}
                                </option>
                            @endforeach
                            @foreach($wallets as $wallet)
                                <option value="{{ $wallet->id }}" data-type="wallet" @selected(old('received_via_type') === 'wallet' && (string) old('received_via_id') === (string) $wallet->id)>
                                    Wallet: {{ $wallet->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('received_via_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300">Reference (optional)</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    </div>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-300">Notes (optional)</label>
                <textarea name="notes" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeEmployeeLoanSettlementModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2.5 text-sm text-white hover:bg-white/10">Cancel</button>
                <button type="submit" class="flex-1 rounded-2xl border border-purple-400/40 bg-gradient-to-r from-purple-500 to-purple-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-purple-500/30 hover:from-purple-600 hover:to-purple-800 transition" id="el_settlement_submit">
                    Settle loan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const elSettlementChannelHints = @json(collect($previews)->map(fn ($p) => $p ? ['ready' => $p->ready, 'reason' => $p->reason ?? null, 'gateway' => $p->gatewayName ?? null] : null));

    function openEmployeeLoanSettlementModal() {
        document.getElementById('employeeLoanSettlementModal')?.classList.remove('hidden');
        syncEmployeeLoanSettlementPanels();
    }
    function closeEmployeeLoanSettlementModal() {
        document.getElementById('employeeLoanSettlementModal')?.classList.add('hidden');
    }
    function syncEmployeeLoanSettlementPanels() {
        const mode = document.getElementById('el_settlement_collection_mode')?.value || 'gateway';
        document.getElementById('el_settlement_gateway_panel')?.classList.toggle('hidden', mode !== 'gateway');
        document.getElementById('el_settlement_treasury_panel')?.classList.toggle('hidden', mode !== 'treasury');
        const submit = document.getElementById('el_settlement_submit');
        if (submit) {
            submit.textContent = mode === 'gateway' ? 'Send settlement prompt' : 'Settle & credit treasury';
        }
        syncElSettlementGatewayHint();
        syncElSettlementTreasuryAccounts();
    }
    function syncElSettlementTreasuryAccounts() {
        const typeSelect = document.getElementById('el_settlement_received_via_type');
        const accountSelect = document.getElementById('el_settlement_received_via_id');
        if (!typeSelect || !accountSelect) return;
        const type = typeSelect.value;
        Array.from(accountSelect.options).forEach(opt => {
            const show = (opt.dataset.type || 'bank') === type;
            opt.hidden = !show;
            opt.disabled = !show;
        });
        const firstVisible = Array.from(accountSelect.options).find(o => !o.hidden);
        if (firstVisible && accountSelect.selectedOptions[0]?.hidden) {
            accountSelect.value = firstVisible.value;
        }
    }
    function syncElSettlementGatewayHint() {
        const channelId = document.getElementById('el_settlement_channel_id')?.value;
        const hint = document.getElementById('el_settlement_gateway_hint');
        if (!hint || !channelId) return;
        const meta = elSettlementChannelHints[channelId];
        if (!meta) { hint.textContent = ''; return; }
        hint.textContent = meta.ready
            ? 'Gateway ready' + (meta.gateway ? ' via ' + meta.gateway + '.' : '.')
            : (meta.reason || 'Use treasury settlement if gateway is unavailable.');
    }
    document.getElementById('el_settlement_collection_mode')?.addEventListener('change', syncEmployeeLoanSettlementPanels);
    document.getElementById('el_settlement_received_via_type')?.addEventListener('change', syncElSettlementTreasuryAccounts);
    document.getElementById('el_settlement_channel_id')?.addEventListener('change', syncElSettlementGatewayHint);
    document.getElementById('employeeLoanSettlementModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeEmployeeLoanSettlementModal();
    });
    @if(old('form_context') === 'settlement' && ($errors->any() || session('error')))
    document.addEventListener('DOMContentLoaded', openEmployeeLoanSettlementModal);
    @endif
    document.addEventListener('DOMContentLoaded', syncEmployeeLoanSettlementPanels);
</script>
@endpush
