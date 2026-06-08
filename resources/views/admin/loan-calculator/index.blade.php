@extends('layouts.admin')

@section('title', 'Loan Calculator | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Loan Calculator',
            'subtitle' => 'Preview repayments using the rate card linked to each customer group and product.',
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <div class="rounded-2xl border border-cyan-400/20 bg-cyan-500/5 p-4 text-sm text-slate-300">
                <p class="font-medium text-cyan-200">How this works</p>
                <ol class="mt-2 list-decimal list-inside space-y-1 text-slate-400">
                    <li>Select a <strong class="text-slate-200">loan product</strong> — only groups under that product appear.</li>
                    <li>Select a <strong class="text-slate-200">customer group</strong> — calculations use that group&apos;s linked rate card.</li>
                    <li>Enter amount and start date — each row shows a tenure option from the group&apos;s active rates (amount bands apply automatically).</li>
                </ol>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2" for="productSelect">Loan product</label>
                    <select id="productSelect" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select product</option>
                        @foreach($loanProducts as $product)
                            <option value="{{ $product->id }}" data-max-amount="{{ $product->max_amount }}">
                                {{ $product->name }} @if($product->company) ({{ $product->company->name }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2" for="groupSelect">Customer group</label>
                    <select id="groupSelect" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40" disabled>
                        <option value="">Select group</option>
                    </select>
                    <p id="groupHint" class="text-xs text-slate-400 mt-1">Groups are filtered by the selected product.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2" for="amountInput">Loan amount (ZMW)</label>
                    <input id="amountInput" type="number" min="1" step="0.01" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="e.g. 10000">
                    <p id="amountHint" class="text-xs text-slate-400 mt-1 hidden"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2" for="startDateInput">Start date</label>
                    <input id="startDateInput" type="date" value="{{ now()->toDateString() }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <p class="text-xs text-slate-400 mt-1">Used for term days and accrual schedules.</p>
                </div>
            </div>

            <div id="groupContextCard" class="hidden rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300 space-y-2">
                <p class="text-xs uppercase tracking-[0.3em] text-cyan-200">Selected group rate card</p>
                <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
                    <div>Rate card: <span id="ctxRateType" class="text-white font-medium">—</span></div>
                    <div>Interest: <span id="ctxInterestBehavior" class="text-white font-medium">—</span></div>
                    <div>Accrual: <span id="ctxAccrual" class="text-white font-medium">—</span></div>
                    <div>Tenures on rate card: <span id="ctxTenures" class="text-white font-medium">—</span></div>
                    <div>Group max tenure: <span id="ctxGroupMaxTenure" class="text-white font-medium">—</span></div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="calculateBtn" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/30 transition">
                    Calculate
                </button>
                <span id="calcStatus" class="text-sm text-slate-400"></span>
            </div>

            <div id="errorBox" class="hidden rounded-2xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-rose-100 text-sm"></div>

            <div id="resultsCard" class="hidden rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-cyan-200">Estimates</p>
                        <h3 class="text-xl font-semibold text-white mt-1">Repayment options</h3>
                        <p id="resultsSubtitle" class="text-sm text-slate-400 mt-1"></p>
                    </div>
                    <div class="text-xs text-slate-400 text-right space-y-1">
                        <div>Principal: <span id="resultAmount" class="text-white font-semibold"></span></div>
                        <div>Rate card: <span id="resultRateType" class="text-white font-semibold"></span></div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-slate-200">
                        <thead>
                            <tr class="text-xs uppercase tracking-[0.25em] text-white/80 border-b border-white/10">
                                <th class="px-3 py-2 text-left">Tenure</th>
                                <th class="px-3 py-2 text-right">Proc. fee</th>
                                <th class="px-3 py-2 text-right">Interest</th>
                                <th class="px-3 py-2 text-right">Total (booked)</th>
                                <th class="px-3 py-2 text-right">Monthly</th>
                                <th class="px-3 py-2 text-center">Term</th>
                                <th class="px-3 py-2 text-center">Interest type</th>
                            </tr>
                        </thead>
                        <tbody id="resultsBody"></tbody>
                    </table>
                </div>
                <p id="resultsLimitNote" class="hidden text-xs text-amber-200/90"></p>
                <p class="text-xs text-slate-500">“Total (booked)” is what the system records at loan creation. For daily accrual products, upfront interest may be projected but not booked until accrual runs.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const productSelect = document.getElementById('productSelect');
    const groupSelect = document.getElementById('groupSelect');
    const amountInput = document.getElementById('amountInput');
    const startDateInput = document.getElementById('startDateInput');
    const calcBtn = document.getElementById('calculateBtn');
    const statusEl = document.getElementById('calcStatus');
    const errorBox = document.getElementById('errorBox');
    const resultsCard = document.getElementById('resultsCard');
    const resultsBody = document.getElementById('resultsBody');
    const resultAmount = document.getElementById('resultAmount');
    const resultRateType = document.getElementById('resultRateType');
    const resultsSubtitle = document.getElementById('resultsSubtitle');
    const groupHint = document.getElementById('groupHint');
    const amountHint = document.getElementById('amountHint');
    const groupContextCard = document.getElementById('groupContextCard');
    const ctxRateType = document.getElementById('ctxRateType');
    const ctxInterestBehavior = document.getElementById('ctxInterestBehavior');
    const ctxAccrual = document.getElementById('ctxAccrual');
    const ctxTenures = document.getElementById('ctxTenures');
    const ctxGroupMaxTenure = document.getElementById('ctxGroupMaxTenure');
    const resultsLimitNote = document.getElementById('resultsLimitNote');

    const routes = {
        groups: @json(route('admin.loan-calculator.groups')),
        calculate: @json(route('admin.loan-calculator.calculate')),
        csrf: @json(csrf_token()),
    };

    const interestBehaviorLabels = {
        upfront_flat: 'Upfront (flat at start)',
        daily_accrual: 'Daily accrual',
        amortized: 'Amortized',
    };

    const fmt = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    let groupsCache = [];

    function selectedProductMaxAmount() {
        const opt = productSelect.selectedOptions[0];
        const v = opt?.dataset?.maxAmount;
        return v && v !== '' ? Number(v) : null;
    }

    function updateAmountHint() {
        const group = groupsCache.find(g => String(g.id) === groupSelect.value);
        const productMax = selectedProductMaxAmount();
        const limits = [];
        if (group?.max_amount) limits.push(`group max ZMW ${fmt(group.max_amount)}`);
        if (productMax) limits.push(`product max ZMW ${fmt(productMax)}`);
        if (limits.length) {
            amountHint.textContent = `Limits: ${limits.join(' · ')}`;
            amountHint.classList.remove('hidden');
        } else {
            amountHint.classList.add('hidden');
        }
    }

    function updateGroupContext() {
        const group = groupsCache.find(g => String(g.id) === groupSelect.value);
        if (!group) {
            groupContextCard.classList.add('hidden');
            return;
        }
        groupContextCard.classList.remove('hidden');
        ctxRateType.textContent = group.rate_type_name || '—';
        ctxInterestBehavior.textContent = interestBehaviorLabels[group.interest_behavior] || group.interest_behavior || '—';
        ctxAccrual.textContent = group.accrual_period ? group.accrual_period.toUpperCase() : '—';
        ctxTenures.textContent = (group.available_tenures || []).length
            ? group.available_tenures.join(', ') + ' mo'
            : 'None configured';
        if (group.max_tenure_months) {
            const allowed = group.allowed_tenures || [];
            ctxGroupMaxTenure.textContent = `${group.max_tenure_months} mo` + (
                allowed.length && allowed.length < (group.available_tenures || []).length
                    ? ` (lending: ${allowed.join(', ')} mo)`
                    : ''
            );
        } else {
            ctxGroupMaxTenure.textContent = 'Not capped';
        }
        updateAmountHint();
    }

    productSelect.addEventListener('change', () => {
        groupSelect.innerHTML = '<option value="">Select group</option>';
        groupSelect.disabled = true;
        groupsCache = [];
        groupContextCard.classList.add('hidden');
        hideError();
        resultsCard.classList.add('hidden');
        updateAmountHint();

        if (!productSelect.value) {
            groupHint.textContent = 'Groups are filtered by the selected product.';
            return;
        }

        statusEl.textContent = 'Loading groups...';
        fetch(`${routes.groups}?loan_product_id=${productSelect.value}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                groupsCache = data.groups || [];
                groupSelect.disabled = false;
                statusEl.textContent = '';
                if (!groupsCache.length) {
                    groupHint.textContent = 'No active groups for this product.';
                    return;
                }
                groupHint.textContent = `${groupsCache.length} group(s) available.`;
                groupsCache.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = g.name + (g.code ? ` (${g.code})` : '');
                    groupSelect.appendChild(opt);
                });
            })
            .catch(() => {
                statusEl.textContent = '';
                showError('Failed to load groups. Please try again.');
            });
    });

    groupSelect.addEventListener('change', () => {
        hideError();
        resultsCard.classList.add('hidden');
        updateGroupContext();
    });

    calcBtn.addEventListener('click', () => {
        hideError();
        resultsCard.classList.add('hidden');

        const productId = productSelect.value;
        const groupId = groupSelect.value;
        const amount = Number(amountInput.value);
        const startDate = startDateInput.value;
        const group = groupsCache.find(g => String(g.id) === groupId);
        const productMax = selectedProductMaxAmount();

        if (!productId || !groupId || !amount) {
            showError('Select product, group, and loan amount first.');
            return;
        }

        const maxAmount = [group?.max_amount, productMax].filter(v => v != null && v > 0);
        const effectiveMax = maxAmount.length ? Math.min(...maxAmount) : null;
        if (effectiveMax && amount > effectiveMax) {
            showError(`Amount exceeds the limit of ZMW ${fmt(effectiveMax)} for this group/product.`);
            return;
        }

        statusEl.textContent = 'Calculating...';
        fetch(routes.calculate, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': routes.csrf,
            },
            body: JSON.stringify({
                loan_product_id: productId,
                customer_group_id: groupId,
                amount: amount,
                start_date: startDate,
            }),
        }).then(async (res) => {
            const data = await res.json();
            statusEl.textContent = '';
            if (!res.ok || data.error) {
                showError(data.error || 'Calculation failed.');
                return;
            }
            renderResults(data);
        }).catch(() => {
            statusEl.textContent = '';
            showError('Calculation failed. Please retry.');
        });
    });

    function renderResults(data) {
        resultAmount.textContent = `ZMW ${fmt(data.amount)}`;
        resultRateType.textContent = data.rate_type_name || '—';
        resultsSubtitle.textContent = [
            data.product_name,
            data.group_name,
            data.interest_behavior ? interestBehaviorLabels[data.interest_behavior] || data.interest_behavior : null,
        ].filter(Boolean).join(' · ');

        resultsBody.innerHTML = '';
        (data.rows || []).forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'border-t border-white/10 hover:bg-white/5 transition';
            const interestNote = row.interest_behavior === 'daily_accrual' && row.projected_interest > row.booked_interest
                ? ` <span class="text-slate-500">(proj. ${fmt(row.projected_interest)})</span>`
                : '';
            const tenureLabel = row.tenure_months + ' mo' + (
                row.exceeds_group_max_tenure
                    ? ' <span class="text-amber-300 text-xs font-normal">(above group limit)</span>'
                    : ''
            );
            tr.innerHTML = `
                <td class="px-3 py-2 text-left text-white font-semibold">${tenureLabel}</td>
                <td class="px-3 py-2 text-right text-slate-300">${row.processing_fee_percentage || '—'}%<br><span class="text-xs">ZMW ${fmt(row.processing_fee)}</span></td>
                <td class="px-3 py-2 text-right">ZMW ${fmt(row.booked_interest ?? row.interest)}${interestNote}</td>
                <td class="px-3 py-2 text-right text-white font-medium">ZMW ${fmt(row.booked_total ?? row.total)}</td>
                <td class="px-3 py-2 text-right text-emerald-200">ZMW ${fmt(row.monthly)}</td>
                <td class="px-3 py-2 text-center text-slate-400">${row.days} days</td>
                <td class="px-3 py-2 text-center text-slate-300 text-xs">${interestBehaviorLabels[row.interest_behavior] || row.interest_behavior || '—'}</td>
            `;
            resultsBody.appendChild(tr);
        });
        if (resultsLimitNote) {
            if (data.group_max_tenure_months && (data.rows || []).some(r => r.exceeds_group_max_tenure)) {
                resultsLimitNote.textContent = `This group allows lending up to ${data.group_max_tenure_months} month(s). Rows marked “above group limit” show rate-card pricing for reference only. Update the group’s max tenure in Customer Groups if staff should book longer terms.`;
                resultsLimitNote.classList.remove('hidden');
            } else {
                resultsLimitNote.textContent = '';
                resultsLimitNote.classList.add('hidden');
            }
        }

        resultsCard.classList.remove('hidden');
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }
    function hideError() {
        errorBox.classList.add('hidden');
        errorBox.textContent = '';
    }
})();
</script>
@endpush
