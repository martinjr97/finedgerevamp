@php
    $outstanding = (float) ($repaymentOutstanding ?? 0);
    $channels = $repaymentChannels ?? collect();
    $previews = $repaymentChannelPreviews ?? [];
    $defaultPhone = old('phone', $defaultRepaymentPhone ?? '');
@endphp

<div id="employeeLoanRepaymentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="rounded-3xl border border-white/10 bg-slate-900 p-6 w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-white mb-1">Record employee loan repayment</h2>
        <p class="text-sm text-slate-300 mb-4">
            Outstanding: <span class="font-semibold text-emerald-300">K {{ number_format($outstanding, 2) }}</span>.
            Collections stay on the employee-loan ledger (not customer loans) and credit treasury when funds are received.
        </p>

        <form method="POST" action="{{ route('admin.hr.employee-loans.repay', $loan) }}" id="employeeLoanRepaymentForm" class="space-y-5">
            @csrf
            <input type="hidden" name="form_context" value="repayment">

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-300">Repayment amount</label>
                    <select name="repayment_type" id="el_repayment_type" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                        <option value="full" @selected(old('repayment_type', 'full') === 'full')>Full outstanding (K {{ number_format($outstanding, 2) }})</option>
                        <option value="partial" @selected(old('repayment_type') === 'partial')>Partial amount</option>
                    </select>
                </div>
                <div id="el_partial_amount_group" class="{{ old('repayment_type') === 'partial' ? '' : 'hidden' }}">
                    <label class="text-sm font-medium text-slate-300">Partial amount (K)</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $outstanding }}" name="amount" id="el_repayment_amount"
                           value="{{ old('amount') }}"
                           class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    @error('amount')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">Effective date</label>
                    <input type="date" name="effective_date" required value="{{ old('effective_date', now()->toDateString()) }}"
                           class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-300">How was this collected?</label>
                <select name="collection_mode" id="el_collection_mode" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                    <option value="gateway" @selected(old('collection_mode', 'gateway') === 'gateway')>Mobile money / gateway (send prompt to employee phone)</option>
                    <option value="treasury" @selected(old('collection_mode') === 'treasury')>Already received — payroll deduction or treasury deposit</option>
                </select>
            </div>

            <div id="el_gateway_panel" class="rounded-2xl border border-cyan-500/30 bg-cyan-500/5 p-4 space-y-4">
                <p class="text-sm text-cyan-100">A payment push is sent to the employee&apos;s mobile number (same flow as customer loan gateway collections).</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-300">Repayment channel</label>
                        <select name="channel_id" id="el_channel_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            @foreach($channels as $channel)
                                @php $preview = $previews[$channel->id] ?? null; @endphp
                                <option value="{{ $channel->id }}" @selected((string) old('channel_id') === (string) $channel->id)>
                                    {{ $channel->name }}@if($preview && ! $preview->ready) (manual / unavailable)@elseif($preview?->ready) (gateway ready)@endif
                                </option>
                            @endforeach
                        </select>
                        @error('channel_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300">Employee mobile number</label>
                        <input type="text" name="phone" id="el_repayment_phone" value="{{ $defaultPhone }}"
                               class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5" placeholder="097…">
                        @error('phone')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p id="el_gateway_hint" class="text-xs text-slate-400"></p>
            </div>

            <div id="el_treasury_panel" class="hidden rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-4">
                <p class="text-sm text-emerald-100">Use when payroll already deducted the installment or cash landed in a company bank/wallet. The selected account is credited on save.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-300">Source</label>
                        <select name="repayment_source" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            <option value="payroll" @selected(old('repayment_source', 'payroll') === 'payroll')>Payroll deduction</option>
                            <option value="bank" @selected(old('repayment_source') === 'bank')>Bank transfer</option>
                            <option value="cash" @selected(old('repayment_source') === 'cash')>Cash</option>
                            <option value="manual" @selected(old('repayment_source') === 'manual')>Other / manual</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300">Treasury account type</label>
                        <select name="received_via_type" id="el_received_via_type" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            <option value="bank" @selected(old('received_via_type', 'bank') === 'bank')>Bank</option>
                            <option value="wallet" @selected(old('received_via_type') === 'wallet')>Wallet</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300">Treasury account</label>
                        <select name="received_via_id" id="el_received_via_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" data-type="bank" @selected(old('received_via_type', 'bank') === 'bank' && (string) old('received_via_id') === (string) $bank->id)>
                                    Bank: {{ $bank->name }} (K {{ number_format((float) $bank->current_balance, 2) }})
                                </option>
                            @endforeach
                            @foreach($wallets as $wallet)
                                <option value="{{ $wallet->id }}" data-type="wallet" @selected(old('received_via_type') === 'wallet' && (string) old('received_via_id') === (string) $wallet->id)>
                                    Wallet: {{ $wallet->name }} (K {{ number_format((float) $wallet->current_balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('received_via_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300">Reference (optional)</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5" placeholder="Payslip / bank ref">
                    </div>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-300">Internal notes (optional)</label>
                <textarea name="notes" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5">{{ old('notes') }}</textarea>
            </div>

            @error('collection_mode')<p class="text-xs text-rose-300">{{ $message }}</p>@enderror

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeEmployeeLoanRepaymentModal()" class="flex-1 rounded-2xl border border-white/10 px-4 py-2.5 text-sm text-white hover:bg-white/10">Cancel</button>
                <button type="submit" class="flex-1 btn-primary rounded-2xl px-4 py-2.5 text-sm font-semibold" id="el_repayment_submit">
                    Record repayment
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const elChannelHints = @json(collect($previews)->map(fn ($p) => $p ? ['ready' => $p->ready, 'reason' => $p->reason ?? null, 'gateway' => $p->gatewayName ?? null] : null));

    function openEmployeeLoanRepaymentModal() {
        document.getElementById('employeeLoanRepaymentModal')?.classList.remove('hidden');
        syncEmployeeLoanRepaymentPanels();
    }
    function closeEmployeeLoanRepaymentModal() {
        document.getElementById('employeeLoanRepaymentModal')?.classList.add('hidden');
    }
    function syncEmployeeLoanRepaymentPanels() {
        const mode = document.getElementById('el_collection_mode')?.value || 'gateway';
        document.getElementById('el_gateway_panel')?.classList.toggle('hidden', mode !== 'gateway');
        document.getElementById('el_treasury_panel')?.classList.toggle('hidden', mode !== 'treasury');
        const submit = document.getElementById('el_repayment_submit');
        if (submit) {
            submit.textContent = mode === 'gateway' ? 'Send payment prompt' : 'Record & credit treasury';
        }
        syncElGatewayHint();
        syncElTreasuryAccounts();
    }
    function syncElPartialAmount() {
        const type = document.getElementById('el_repayment_type')?.value;
        document.getElementById('el_partial_amount_group')?.classList.toggle('hidden', type !== 'partial');
    }
    function syncElTreasuryAccounts() {
        const typeSelect = document.getElementById('el_received_via_type');
        const accountSelect = document.getElementById('el_received_via_id');
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
    function syncElGatewayHint() {
        const channelId = document.getElementById('el_channel_id')?.value;
        const hint = document.getElementById('el_gateway_hint');
        if (!hint || !channelId) return;
        const meta = elChannelHints[channelId];
        if (!meta) { hint.textContent = ''; return; }
        hint.textContent = meta.ready
            ? 'Gateway ready' + (meta.gateway ? ' via ' + meta.gateway + '.' : '.')
            : (meta.reason || 'This channel may require treasury recording instead.');
    }
    document.getElementById('el_collection_mode')?.addEventListener('change', syncEmployeeLoanRepaymentPanels);
    document.getElementById('el_repayment_type')?.addEventListener('change', syncElPartialAmount);
    document.getElementById('el_received_via_type')?.addEventListener('change', syncElTreasuryAccounts);
    document.getElementById('el_channel_id')?.addEventListener('change', syncElGatewayHint);
    document.getElementById('employeeLoanRepaymentModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeEmployeeLoanRepaymentModal();
    });
    @if(old('form_context', 'repayment') === 'repayment' && $errors->any())
    document.addEventListener('DOMContentLoaded', openEmployeeLoanRepaymentModal);
    @endif
    document.addEventListener('DOMContentLoaded', function () {
        syncElPartialAmount();
        syncEmployeeLoanRepaymentPanels();
    });
</script>
@endpush
