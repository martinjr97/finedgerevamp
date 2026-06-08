@extends('layouts.admin')

@section('title', 'Initiate Repayment | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @php
            $headerButtons = [
                [
                    'action' => 'secondary',
                    'text' => 'Customer Repayments',
                    'href' => route('admin.customers.repayments', $customer),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
            ];
            if (! empty($returnToLoanUrl)) {
                $headerButtons[] = [
                    'action' => 'secondary',
                    'text' => 'Back to Loan',
                    'href' => $returnToLoanUrl,
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ];
            }
            $headerButtons[] = [
                'action' => 'secondary',
                'text' => 'Back to Customer',
                'href' => route('admin.customers.show', $customer),
                'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
            ];
            $defaultRepaymentType = old('repayment_type', isset($preselectedLoan) && $preselectedLoan ? 'partial' : 'full');
            $defaultLoanId = old('loan_id', isset($preselectedLoan) && $preselectedLoan ? $preselectedLoan->id : null);
            $defaultAmount = old('amount', isset($preselectedLoan) && $preselectedLoan ? $preselectedLoan->outstanding_balance : null);
        @endphp

        @include('partials.admin.page-header', [
            'title' => 'Initiate Repayment - ' . $customer->full_name,
            'description' => 'Submit a repayment from admin portal (automated gateway or manual approval flow).',
            'buttons' => $headerButtons,
        ])

        @if(isset($preselectedLoan) && $preselectedLoan)
            <div class="rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100">
                Recording repayment for loan
                <a href="{{ route('admin.loans.show', $preselectedLoan) }}" class="font-semibold text-white hover:text-cyan-200 underline underline-offset-2">
                    {{ $preselectedLoan->loan_number }}
                </a>
                — outstanding ZMW {{ number_format((float) $preselectedLoan->outstanding_balance, 2) }}.
                You can still change the repayment type below if needed.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-emerald-200">Outstanding Balance</p>
                <p class="mt-2 text-2xl font-bold text-emerald-300">ZMW {{ number_format($totals['outstanding'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-amber-200">Overdue Balance</p>
                <p class="mt-2 text-2xl font-bold text-amber-300">ZMW {{ number_format($totals['overdue'], 2) }}</p>
            </div>
        </div>

        <form id="repaymentForm" method="POST" action="{{ route('admin.customers.repayments.store', $customer) }}" class="space-y-6 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-200">Repayment Type</label>
                    <select name="repayment_type" id="repayment_type" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                        <option value="full" @selected($defaultRepaymentType === 'full')>Full Outstanding</option>
                        <option value="overdue" @selected($defaultRepaymentType === 'overdue')>Overdue Amount</option>
                        <option value="partial" @selected($defaultRepaymentType === 'partial')>Partial Payment</option>
                    </select>
                    <p id="repayment_type_hint" class="mt-2 text-xs text-slate-400"></p>
                    @error('repayment_type')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-200">Recovery Method</label>
                    <select name="recovery_method" id="recovery_method" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                        @foreach($recoveryMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('recovery_method', \App\Support\RepaymentRecoveryMethod::NORMAL) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Select how this repayment was recovered or initiated.</p>
                    @error('recovery_method')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-200">Repayment Channel</label>
                    <select name="channel_id" id="channel_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                        @foreach($channels as $channel)
                            <option value="{{ $channel->id }}" data-integrated="{{ $channel->is_repayment_integrated ? '1' : '0' }}" @selected((string) old('channel_id') === (string) $channel->id)>
                                {{ $channel->name }} ({{ $channel->is_repayment_integrated ? 'Integrated' : 'Manual' }})
                            </option>
                        @endforeach
                    </select>
                    @error('channel_id')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="loan_id_group" class="{{ $defaultRepaymentType === 'partial' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-slate-200">Loan (for partial payment)</label>
                    <select name="loan_id" id="loan_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select Loan</option>
                        @foreach($activeLoans as $loan)
                            @php
                                $ledger = $loanLedgerById[$loan->id] ?? [
                                    'outstanding' => (float) $loan->outstanding_balance,
                                    'expected_settlement' => (float) $loan->total_amount,
                                    'net_paid' => (float) $loan->amount_paid,
                                ];
                            @endphp
                            <option
                                value="{{ $loan->id }}"
                                data-outstanding="{{ number_format($ledger['outstanding'], 2, '.', '') }}"
                                data-expected-settlement="{{ number_format($ledger['expected_settlement'], 2, '.', '') }}"
                                data-net-paid="{{ number_format($ledger['net_paid'], 2, '.', '') }}"
                                @selected((string) $defaultLoanId === (string) $loan->id)
                            >
                                {{ $loan->loan_number }} - Outstanding ZMW {{ number_format($ledger['outstanding'], 2) }}
                            </option>
                        @endforeach
                    </select>
                    @error('loan_id')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="amount_group" class="{{ $defaultRepaymentType === 'partial' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-slate-200">Amount (Partial)</label>
                    <input type="number" name="amount" id="amount" value="{{ $defaultAmount }}" min="0.01" step="0.01" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="0.00">
                    <p id="amount_limit_hint" class="mt-1 text-xs text-slate-400"></p>
                    <div id="overpayment_warning" class="hidden mt-3 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-100 space-y-3">
                        <p class="font-semibold text-amber-200">Payment above current outstanding balance</p>
                        <p>This payment is greater than the current outstanding balance. Any amount needed to settle the full loan obligation will be applied to the loan first. Only amounts above the <strong>total expected settlement</strong> are recorded as customer credit/suspense and can be refunded later. Excess below that threshold may pay ahead on the schedule.</p>
                        <dl class="grid gap-2 sm:grid-cols-2 text-xs">
                            <div class="flex justify-between gap-2 border-b border-amber-500/20 pb-1"><dt>Amount entered</dt><dd id="preview_amount_entered" class="font-semibold text-white">—</dd></div>
                            <div class="flex justify-between gap-2 border-b border-amber-500/20 pb-1"><dt>Current outstanding</dt><dd id="preview_outstanding" class="font-semibold text-white">—</dd></div>
                            <div class="flex justify-between gap-2 border-b border-amber-500/20 pb-1"><dt>Amount reducing outstanding</dt><dd id="preview_applied_to_loan" class="font-semibold text-white">—</dd></div>
                            <div class="flex justify-between gap-2 border-b border-amber-500/20 pb-1"><dt>Excess above outstanding</dt><dd id="preview_excess_outstanding" class="font-semibold text-white">—</dd></div>
                            <div class="flex justify-between gap-2 sm:col-span-2 border-b border-amber-500/20 pb-1"><dt>Estimated customer credit after payment</dt><dd id="preview_customer_credit" class="font-semibold text-amber-200">—</dd></div>
                        </dl>
                        <p id="overpayment_advance_note" class="hidden text-xs text-amber-200/90"></p>
                    </div>
                    @error('amount')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="overpayment_reason_group" class="hidden md:col-span-2 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 space-y-4">
                    <div>
                        <label for="overpayment_reason" class="block text-sm font-medium text-amber-200">Overpayment / suspense reason <span class="text-red-400">*</span></label>
                        <select name="overpayment_reason" id="overpayment_reason" class="mt-2 w-full rounded-2xl bg-white/10 border border-amber-500/30 text-white px-4 py-3 focus:border-amber-400 focus:ring-amber-400/40">
                            <option value="">Select reason</option>
                            @foreach($overpaymentReasons as $reason)
                                <option value="{{ $reason }}" @selected(old('overpayment_reason') === $reason)>{{ $reason }}</option>
                            @endforeach
                        </select>
                        @error('overpayment_reason')
                            <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div id="overpayment_reason_other_group" class="hidden">
                        <label for="overpayment_reason_other" class="block text-sm font-medium text-amber-200">Describe reason <span class="text-red-400">*</span></label>
                        <textarea name="overpayment_reason_other" id="overpayment_reason_other" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-amber-500/30 text-white px-4 py-3 focus:border-amber-400 focus:ring-amber-400/40" placeholder="Provide details for this overpayment">{{ old('overpayment_reason_other') }}</textarea>
                        @error('overpayment_reason_other')
                            <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <input type="hidden" name="overpayment_confirmed" id="overpayment_confirmed" value="{{ old('overpayment_confirmed') ? '1' : '' }}">
                    @error('overpayment_confirmed')
                        <p class="text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="phone_number_group" class="hidden">
                    <label for="phone_number" class="block text-sm font-medium text-slate-200">Mobile money number <span class="text-slate-500 font-normal">(for payment prompt)</span></label>
                    <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number', $customer->phone) }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
                    <p class="mt-1 text-xs text-slate-400">Number that will receive the integrated channel payment request (USSD/app prompt). Defaults to the customer profile number.</p>
                    @error('phone_number')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-200">Submission Mode</label>
                    <select name="submission_mode" id="submission_mode" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" required>
                        <option value="auto" @selected(old('submission_mode', 'auto') === 'auto')>Auto (Send to Gateway)</option>
                        <option value="manual" @selected(old('submission_mode') === 'manual')>Manual (Pending Approval)</option>
                    </select>
                    @error('submission_mode')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="manual_source_group" class="hidden">
                    <label class="block text-sm font-medium text-slate-200">Manual Payment Source</label>
                    <select name="manual_source" id="manual_source" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select source</option>
                        <option value="cash" @selected(old('manual_source') === 'cash')>Cash</option>
                        <option value="bank" @selected(old('manual_source') === 'bank')>Bank</option>
                        <option value="wallet" @selected(old('manual_source') === 'wallet')>Wallet</option>
                    </select>
                    @error('manual_source')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="bank_group" class="hidden">
                    <label class="block text-sm font-medium text-slate-200">Bank</label>
                    <select name="bank_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select bank</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>{{ $bank->display_name }}</option>
                        @endforeach
                    </select>
                    @error('bank_id')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div id="wallet_group" class="hidden">
                    <label class="block text-sm font-medium text-slate-200">Wallet</label>
                    <select name="wallet_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select wallet</option>
                        @foreach($wallets as $wallet)
                            <option value="{{ $wallet->id }}" @selected((string) old('wallet_id') === (string) $wallet->id)>{{ $wallet->display_name }}</option>
                        @endforeach
                    </select>
                    @error('wallet_id')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                @if($cashRegisters->count() > 1)
                    <div id="cash_register_group" class="hidden">
                        <label class="block text-sm font-medium text-slate-200">Cash Register</label>
                        <select name="cash_register_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            @foreach($cashRegisters as $register)
                                <option value="{{ $register->id }}" @selected((string) old('cash_register_id', $cashRegisters->first()->id) === (string) $register->id)>{{ $register->display_name }}</option>
                            @endforeach
                        </select>
                        @error('cash_register_id')
                            <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div id="payment_references_section" class="md:col-span-2 rounded-2xl border border-white/10 bg-white/5 p-5 space-y-4">
                    <div>
                        <p class="text-sm font-semibold text-white">Payment references</p>
                        <p id="references_section_intro" class="mt-1 text-xs text-slate-400"></p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="external_reference" class="block text-sm font-medium text-slate-200">
                                <span id="external_reference_label">Receipt / deposit reference</span>
                                <span class="text-slate-500 font-normal">(optional)</span>
                            </label>
                            <input type="text" name="external_reference" id="external_reference" value="{{ old('external_reference') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 font-mono text-sm" placeholder="">
                            <p id="external_reference_hint" class="mt-1.5 text-xs text-slate-400 leading-relaxed"></p>
                            @error('external_reference')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="external_transaction_id" class="block text-sm font-medium text-slate-200">
                                <span id="external_transaction_id_label">Provider transaction ID</span>
                                <span class="text-slate-500 font-normal">(optional)</span>
                            </label>
                            <input type="text" name="external_transaction_id" id="external_transaction_id" value="{{ old('external_transaction_id') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 font-mono text-sm" placeholder="">
                            <p id="external_transaction_id_hint" class="mt-1.5 text-xs text-slate-400 leading-relaxed"></p>
                            @error('external_transaction_id')
                                <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 border-t border-white/10 pt-3">
                        <strong class="text-slate-400">Tip:</strong>
                        <span id="references_tip_text">Use the receipt reference for your internal or bank slip number; use the transaction ID for the unique ID from MTN, Airtel, or the bank switch.</span>
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-200">Notes</label>
                    <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Optional approval or payment notes">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.customers.repayments', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                    Cancel
                </a>
                <button type="submit" id="submit_repayment_btn" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/30 hover:from-emerald-600 hover:to-teal-700 transition">
                    Submit Repayment
                </button>
            </div>
        </form>

        <div id="overpayment_confirm_modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg rounded-3xl border border-amber-500/30 bg-slate-900 p-6 shadow-2xl space-y-4">
                <h3 class="text-lg font-semibold text-white">Confirm overpayment</h3>
                <p class="text-sm text-slate-300">You are recording a payment above the current outstanding balance.</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Outstanding</dt><dd id="modal_outstanding" class="font-semibold text-white">—</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Payment amount</dt><dd id="modal_payment_amount" class="font-semibold text-white">—</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Customer credit / suspense</dt><dd id="modal_customer_credit" class="font-semibold text-amber-300">—</dd></div>
                </dl>
                <p class="text-xs text-slate-400">This can later be refunded from the repayment history if it becomes customer credit.</p>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" id="overpayment_modal_cancel" class="rounded-2xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">Cancel</button>
                    <button type="button" id="overpayment_modal_confirm" class="rounded-2xl bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-amber-400 transition">Confirm &amp; submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const repaymentType = document.getElementById('repayment_type');
        const loanGroup = document.getElementById('loan_id_group');
        const amountGroup = document.getElementById('amount_group');
        const loanSelect = document.getElementById('loan_id');
        const amountInput = document.getElementById('amount');
        const amountLimitHint = document.getElementById('amount_limit_hint');
        const repaymentTypeHint = document.getElementById('repayment_type_hint');
        const repaymentForm = document.getElementById('repaymentForm');
        const overpaymentWarning = document.getElementById('overpayment_warning');
        const overpaymentReasonGroup = document.getElementById('overpayment_reason_group');
        const overpaymentReason = document.getElementById('overpayment_reason');
        const overpaymentReasonOtherGroup = document.getElementById('overpayment_reason_other_group');
        const overpaymentReasonOther = document.getElementById('overpayment_reason_other');
        const overpaymentConfirmed = document.getElementById('overpayment_confirmed');
        const overpaymentModal = document.getElementById('overpayment_confirm_modal');
        const overpaymentModalCancel = document.getElementById('overpayment_modal_cancel');
        const overpaymentModalConfirm = document.getElementById('overpayment_modal_confirm');
        let pendingOverpaymentSubmit = false;

        const totalOutstanding = {{ json_encode((float) $totals['outstanding']) }};
        const totalOverdue = {{ json_encode((float) $totals['overdue']) }};
        const activeLoanCount = {{ $activeLoans->count() }};

        const channelSelect = document.getElementById('channel_id');
        const phoneNumberGroup = document.getElementById('phone_number_group');
        const referencesSectionIntro = document.getElementById('references_section_intro');
        const externalReferenceInput = document.getElementById('external_reference');
        const externalTransactionIdInput = document.getElementById('external_transaction_id');
        const externalReferenceHint = document.getElementById('external_reference_hint');
        const externalTransactionIdHint = document.getElementById('external_transaction_id_hint');
        const referencesTipText = document.getElementById('references_tip_text');
        const submissionMode = document.getElementById('submission_mode');
        const manualSourceGroup = document.getElementById('manual_source_group');
        const manualSource = document.getElementById('manual_source');
        const bankGroup = document.getElementById('bank_group');
        const walletGroup = document.getElementById('wallet_group');
        const cashRegisterGroup = document.getElementById('cash_register_group');

        function formatZmw(amount) {
            const value = Number(amount);
            if (Number.isNaN(value)) {
                return 'ZMW 0.00';
            }
            return 'ZMW ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function selectedLoanContext() {
            if (!loanSelect) {
                return { outstanding: 0, expected: 0, netPaid: 0 };
            }
            const option = loanSelect.options[loanSelect.selectedIndex];
            return {
                outstanding: parseFloat(option?.dataset?.outstanding || '0') || 0,
                expected: parseFloat(option?.dataset?.expectedSettlement || '0') || 0,
                netPaid: parseFloat(option?.dataset?.netPaid || '0') || 0,
            };
        }

        function computeOverpaymentPreview(amount) {
            const ctx = selectedLoanContext();
            const appliedToLoan = Math.min(amount, ctx.outstanding);
            const excessAboveOutstanding = Math.max(0, amount - ctx.outstanding);
            const projectedCredit = Math.max(0, ctx.netPaid + amount - ctx.expected);

            return {
                outstanding: ctx.outstanding,
                expected: ctx.expected,
                appliedToLoan,
                excessAboveOutstanding,
                projectedCredit,
            };
        }

        function isOverpaymentAmount(amount) {
            const ctx = selectedLoanContext();
            return amount > ctx.outstanding + 0.0001;
        }

        function updateRepaymentTypeHint() {
            if (!repaymentTypeHint) {
                return;
            }

            if (repaymentType.value === 'full') {
                repaymentTypeHint.textContent = 'Collects the full outstanding balance of '
                    + formatZmw(totalOutstanding)
                    + ' across '
                    + activeLoanCount
                    + ' active loan(s). Amount is calculated automatically.';
            } else if (repaymentType.value === 'overdue') {
                repaymentTypeHint.textContent = 'Collects the overdue balance of '
                    + formatZmw(totalOverdue)
                    + '. Amount is calculated automatically.';
            } else {
                repaymentTypeHint.textContent = 'Enter the amount collected. Payments above the current outstanding balance are allowed and may create customer credit/suspense once the full loan obligation is met.';
            }
        }

        function updateOverpaymentUi() {
            const isPartial = repaymentType.value === 'partial';
            const amount = parseFloat(amountInput?.value || '0');
            const showOverpayment = isPartial && !Number.isNaN(amount) && amount > 0 && isOverpaymentAmount(amount);
            const preview = showOverpayment ? computeOverpaymentPreview(amount) : null;

            if (overpaymentWarning) {
                overpaymentWarning.classList.toggle('hidden', !showOverpayment);
            }
            if (overpaymentReasonGroup) {
                overpaymentReasonGroup.classList.toggle('hidden', !showOverpayment);
            }

            if (showOverpayment && preview) {
                document.getElementById('preview_amount_entered').textContent = formatZmw(amount);
                document.getElementById('preview_outstanding').textContent = formatZmw(preview.outstanding);
                document.getElementById('preview_applied_to_loan').textContent = formatZmw(preview.appliedToLoan);
                document.getElementById('preview_excess_outstanding').textContent = formatZmw(preview.excessAboveOutstanding);
                document.getElementById('preview_customer_credit').textContent = formatZmw(preview.projectedCredit);

                const advanceNote = document.getElementById('overpayment_advance_note');
                if (advanceNote) {
                    if (preview.projectedCredit <= 0 && preview.excessAboveOutstanding > 0) {
                        advanceNote.textContent = 'The excess above current outstanding will be applied ahead on the repayment schedule until the loan is fully settled.';
                        advanceNote.classList.remove('hidden');
                    } else {
                        advanceNote.classList.add('hidden');
                    }
                }
            }

            if (overpaymentReasonOtherGroup && overpaymentReason) {
                const showOther = showOverpayment && overpaymentReason.value === 'Other';
                overpaymentReasonOtherGroup.classList.toggle('hidden', !showOther);
                if (overpaymentReasonOther) {
                    overpaymentReasonOther.required = showOther;
                }
            }

            if (showOverpayment && overpaymentReason) {
                overpaymentReason.required = true;
            } else if (overpaymentReason) {
                overpaymentReason.required = false;
            }

            if (!showOverpayment && overpaymentConfirmed) {
                overpaymentConfirmed.value = '';
                pendingOverpaymentSubmit = false;
            }
        }

        function updateAmountHint() {
            if (!amountInput || repaymentType.value !== 'partial') {
                if (amountLimitHint) {
                    amountLimitHint.textContent = '';
                }
                updateOverpaymentUi();
                return;
            }

            const ctx = selectedLoanContext();
            amountInput.removeAttribute('max');
            amountInput.required = true;

            if (ctx.outstanding > 0) {
                if (amountLimitHint) {
                    amountLimitHint.textContent = 'Current outstanding balance: ' + formatZmw(ctx.outstanding)
                        + '. Payments above this amount will be recorded as customer credit/suspense once the full loan obligation is met.';
                }
            } else if (amountLimitHint) {
                amountLimitHint.textContent = 'Select a loan to see the current outstanding balance.';
            }

            updateOverpaymentUi();
        }

        function togglePartialFields() {
            const isPartial = repaymentType.value === 'partial';
            loanGroup.classList.toggle('hidden', !isPartial);
            amountGroup.classList.toggle('hidden', !isPartial);
            updateRepaymentTypeHint();
            updateAmountHint();
        }

        function channelIsIntegrated() {
            const selected = channelSelect?.options[channelSelect.selectedIndex];
            return selected?.dataset?.integrated === '1';
        }

        function toggleChannelDependentFields() {
            const isIntegrated = channelIsIntegrated();

            if (phoneNumberGroup) {
                phoneNumberGroup.classList.toggle('hidden', !isIntegrated);
            }

            if (referencesSectionIntro) {
                referencesSectionIntro.textContent = isIntegrated
                    ? 'For integrated channels, references are usually returned by the payment provider after the customer approves the prompt. You can still enter them now if you already have proof of payment.'
                    : 'For manual collections (cash, bank deposit, etc.), enter the references from the proof of payment you received — not from Havencrest.';
            }

            if (externalReferenceHint) {
                externalReferenceHint.textContent = isIntegrated
                    ? 'Your receipt or internal collection reference, if known before the gateway responds. Often auto-filled after a successful prompt.'
                    : 'From the proof you hold: bank deposit slip number, teller receipt no., mobile-money SMS reference, or your branch collection reference.';
            }

            if (externalTransactionIdHint) {
                externalTransactionIdHint.textContent = isIntegrated
                    ? 'Unique ID from the payment gateway (e.g. MTN/Airtel transaction ID). Usually populated automatically when the provider confirms payment.'
                    : 'The bank or wallet’s own transaction ID — only if it is different from the receipt/deposit reference above.';
            }

            if (externalReferenceInput) {
                externalReferenceInput.placeholder = isIntegrated
                    ? 'e.g. RCP-2026-00123 or leave blank'
                    : 'e.g. DEP-SLIP-88421 or TELLER-RCPT-55';
            }

            if (externalTransactionIdInput) {
                externalTransactionIdInput.placeholder = isIntegrated
                    ? 'Filled by gateway after approval'
                    : 'e.g. FT260524ABC123 (bank) or MM-998877';
            }

            if (referencesTipText) {
                referencesTipText.textContent = isIntegrated
                    ? 'Receipt reference = your or provider’s collection reference. Transaction ID = the gateway’s unique payment ID after the customer pays.'
                    : 'Receipt reference = slip or receipt number you filed the payment under. Transaction ID = separate bank/wallet ID on the transfer advice, if any.';
            }
        }

        function syncSubmissionModeWithChannel() {
            const isIntegrated = channelIsIntegrated();

            if (!isIntegrated) {
                submissionMode.value = 'manual';
            }

            const autoOption = Array.from(submissionMode.options).find((option) => option.value === 'auto');
            if (autoOption) {
                autoOption.disabled = !isIntegrated;
                autoOption.text = isIntegrated ? 'Auto (Send to Gateway)' : 'Auto (Unavailable for this channel)';
            }

            toggleChannelDependentFields();
            toggleManualFields();
        }

        function toggleManualFields() {
            const isManual = submissionMode.value === 'manual';
            manualSourceGroup.classList.toggle('hidden', !isManual);

            if (!isManual) {
                bankGroup?.classList.add('hidden');
                walletGroup?.classList.add('hidden');
                cashRegisterGroup?.classList.add('hidden');
                return;
            }

            bankGroup?.classList.toggle('hidden', manualSource.value !== 'bank');
            walletGroup?.classList.toggle('hidden', manualSource.value !== 'wallet');
            cashRegisterGroup?.classList.toggle('hidden', manualSource.value !== 'cash');
        }

        function showOverpaymentModal(amount) {
            const preview = computeOverpaymentPreview(amount);
            document.getElementById('modal_outstanding').textContent = formatZmw(preview.outstanding);
            document.getElementById('modal_payment_amount').textContent = formatZmw(amount);
            document.getElementById('modal_customer_credit').textContent = formatZmw(preview.projectedCredit);
            overpaymentModal?.classList.remove('hidden');
        }

        function hideOverpaymentModal() {
            overpaymentModal?.classList.add('hidden');
            pendingOverpaymentSubmit = false;
        }

        repaymentType.addEventListener('change', togglePartialFields);
        if (loanSelect) {
            loanSelect.addEventListener('change', updateAmountHint);
        }
        if (amountInput) {
            amountInput.addEventListener('input', updateAmountHint);
        }
        if (overpaymentReason) {
            overpaymentReason.addEventListener('change', updateOverpaymentUi);
        }
        channelSelect.addEventListener('change', syncSubmissionModeWithChannel);
        submissionMode.addEventListener('change', toggleManualFields);
        manualSource.addEventListener('change', toggleManualFields);
        overpaymentModalCancel?.addEventListener('click', hideOverpaymentModal);
        overpaymentModalConfirm?.addEventListener('click', function () {
            if (overpaymentConfirmed) {
                overpaymentConfirmed.value = '1';
            }
            pendingOverpaymentSubmit = true;
            overpaymentModal?.classList.add('hidden');
            repaymentForm?.requestSubmit();
        });

        if (repaymentForm) {
            repaymentForm.addEventListener('submit', function (event) {
                if (repaymentType.value !== 'partial') {
                    return;
                }

                const amount = parseFloat(amountInput?.value || '0');

                if (!loanSelect?.value) {
                    event.preventDefault();
                    alert('Please select a loan for the partial payment.');
                    return;
                }

                if (Number.isNaN(amount) || amount <= 0) {
                    event.preventDefault();
                    alert('Please enter a valid repayment amount.');
                    return;
                }

                if (isOverpaymentAmount(amount)) {
                    if (!overpaymentReason?.value) {
                        event.preventDefault();
                        alert('Please select an overpayment / suspense reason.');
                        return;
                    }
                    if (overpaymentReason.value === 'Other' && !overpaymentReasonOther?.value?.trim()) {
                        event.preventDefault();
                        alert('Please describe the overpayment reason.');
                        return;
                    }
                    if (!pendingOverpaymentSubmit && overpaymentConfirmed?.value !== '1') {
                        event.preventDefault();
                        showOverpaymentModal(amount);
                        return;
                    }
                }
            });
        }

        togglePartialFields();
        syncSubmissionModeWithChannel();
        toggleManualFields();
        toggleChannelDependentFields();
    })();
</script>
@endpush
