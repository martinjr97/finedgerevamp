@extends('layouts.admin')

@section('title', 'Creditor Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Creditor Details',
            'buttons' => array_filter([
                (float) $creditor->amount > 0 && auth('admin')->user()?->can('financial-transactions.create') ? [
                    'action' => 'create',
                    'text' => 'Record Payment',
                    'href' => route('admin.financial-transactions.expense.create', [
                        'creditor_id' => $creditor->id,
                        'category' => \App\Services\CreditorBalanceService::CREDITOR_LOAN_REPAYMENT_CODE,
                    ]),
                ] : null,
                (float) $creditor->amount > 0 && auth('admin')->user()?->can('creditors.update') ? [
                    'action' => 'primary',
                    'text' => 'Convert / Transfer',
                    'href' => '#',
                    'attributes' => ['onclick' => "document.getElementById('convert-modal').showModal(); return false;"],
                ] : null,
                [
                    'action' => 'edit',
                    'text' => 'Edit Creditor',
                    'href' => route('admin.creditors.edit', $creditor),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
                    'can' => auth('admin')->user()?->can('creditors.update'),
                ],
            ]),
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-sm text-slate-400">Name</label>
                    <p class="text-white font-medium">{{ $creditor->name }}</p>
                </div>
                <div>
                    <label class="text-sm text-slate-400">Outstanding Amount</label>
                    <p class="text-rose-300 font-semibold text-lg">{{ number_format($creditor->amount, 2) }}</p>
                    @if($totalPayments > 0)
                        <p class="text-xs text-slate-400 mt-1">
                            Original: {{ number_format($creditor->amount + $totalPayments, 2) }}
                            · Paid: {{ number_format($totalPayments, 2) }}
                        </p>
                    @endif
                </div>
                @if($creditor->due_date)
                <div>
                    <label class="text-sm text-slate-400">Due Date</label>
                    <p class="text-white font-medium">{{ $creditor->due_date->format('F d, Y') }}</p>
                </div>
                @endif
                <div>
                    <label class="text-sm text-slate-400">Status</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $creditor->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                            {{ $creditor->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </p>
                </div>
                @if($creditor->description)
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-400">Description</label>
                    <p class="text-white">{{ $creditor->description }}</p>
                </div>
                @endif
                @if($creditor->notes)
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-400">Notes</label>
                    <p class="text-white">{{ $creditor->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        @if($creditor->conversions->isNotEmpty())
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400 mb-4">Conversion History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-white/10">
                            <th class="pb-3 pr-4">Date</th>
                            <th class="pb-3 pr-4 text-right">Amount</th>
                            <th class="pb-3 pr-4">Destination</th>
                            <th class="pb-3 pr-4">Notes</th>
                            <th class="pb-3">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($creditor->conversions as $conversion)
                        <tr>
                            <td class="py-3 pr-4 text-white">{{ $conversion->created_at->format('d M Y H:i') }}</td>
                            <td class="py-3 pr-4 text-right text-white font-medium">{{ number_format($conversion->amount, 2) }}</td>
                            <td class="py-3 pr-4 text-slate-300">
                                @if(strtoupper($conversion->destination_type) === 'BANK' && $conversion->destinationBank)
                                    {{ $conversion->destinationBank->name }}
                                @elseif(strtoupper($conversion->destination_type) === 'WALLET' && $conversion->destinationWallet)
                                    {{ $conversion->destinationWallet->name }}
                                @else
                                    {{ $conversion->destination_type }} #{{ $conversion->destination_id }}
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-400">{{ Str::limit($conversion->notes ?? '—', 40) }}</td>
                            <td class="py-3 text-slate-400">{{ trim(($conversion->createdBy->first_name ?? '').' '.($conversion->createdBy->last_name ?? '')) ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400 mb-4">Payment History</h2>
            @if($payments->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-white/10">
                                <th class="pb-3 pr-4">Date</th>
                                <th class="pb-3 pr-4 text-right">Amount</th>
                                <th class="pb-3 pr-4">Source</th>
                                <th class="pb-3 pr-4">Reference</th>
                                <th class="pb-3 pr-4">Description</th>
                                <th class="pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($payments as $payment)
                            <tr>
                                <td class="py-3 pr-4 text-white">{{ $payment->transaction_date->format('d M Y') }}</td>
                                <td class="py-3 pr-4 text-right text-white font-medium">{{ number_format($payment->amount, 2) }}</td>
                                <td class="py-3 pr-4 text-slate-300">
                                    {{ $payment->sourceBank?->name ?? $payment->sourceWallet?->name ?? '—' }}
                                </td>
                                <td class="py-3 pr-4 text-slate-400">{{ $payment->reference_number ?? '—' }}</td>
                                <td class="py-3 pr-4 text-slate-400">{{ Str::limit($payment->description, 50) }}</td>
                                <td class="py-3">
                                    <a href="{{ route('admin.financial-transactions.show', $payment) }}" class="text-cyan-300 hover:text-cyan-200 text-xs font-semibold">View</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-white/10">
                                <td class="pt-3 pr-4 text-slate-300 font-semibold">Total</td>
                                <td class="pt-3 pr-4 text-right text-emerald-300 font-semibold">{{ number_format($totalPayments, 2) }}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="text-slate-400 text-sm">No payments have been recorded against this creditor yet.</p>
            @endif
        </div>
    </div>

    @if((float) $creditor->amount > 0 && auth('admin')->user()?->can('creditors.update'))
    <dialog id="convert-modal" class="p-0 w-full max-w-lg border-0 bg-transparent backdrop:bg-slate-900/50">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-xl space-y-4">
        <form method="POST" action="{{ route('admin.creditors.convert', $creditor) }}" class="space-y-4">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">Convert to Bank or Wallet</h3>
                    <p class="text-sm text-muted mt-1">Reduces creditor liability and credits the selected account.</p>
                </div>
                <button type="button" onclick="document.getElementById('convert-modal').close()" class="text-muted hover:text-primary text-2xl leading-none">&times;</button>
            </div>

            @if($errors->any())
                <div class="rounded-2xl border border-rose-400/30 bg-rose-500/10 p-3 text-sm text-rose-200">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="text-sm font-medium">Amount (ZMW)</label>
                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $creditor->amount }}" value="{{ old('amount', number_format($creditor->amount, 2, '.', '')) }}" required class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                <p class="mt-1 text-xs text-muted">Maximum: {{ number_format($creditor->amount, 2) }}</p>
            </div>

            <div>
                <label class="text-sm font-medium">Destination type</label>
                <select name="destination_type" id="convert_destination_type" required class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select type</option>
                    <option value="bank" @selected(old('destination_type') === 'bank')>Bank</option>
                    <option value="wallet" @selected(old('destination_type') === 'wallet')>Wallet</option>
                </select>
            </div>

            <div id="convert_bank_field" class="hidden">
                <label class="text-sm font-medium">Bank account</label>
                <select id="convert_bank_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select bank</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(old('destination_id') == $bank->id)>{{ $bank->name }}</option>
                    @endforeach
                </select>
            </div>

            <div id="convert_wallet_field" class="hidden">
                <label class="text-sm font-medium">Wallet</label>
                <select id="convert_wallet_id" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select wallet</option>
                    @foreach($wallets as $wallet)
                        <option value="{{ $wallet->id }}" @selected(old('destination_id') == $wallet->id)>{{ $wallet->name }}</option>
                    @endforeach
                </select>
            </div>

            <input type="hidden" name="destination_id" id="convert_destination_id" value="{{ old('destination_id') }}">

            <div>
                <label class="text-sm font-medium">Notes (optional)</label>
                <textarea name="notes" rows="2" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('convert-modal').close()" class="rounded-2xl border border-white/10 px-4 py-2 text-sm text-muted hover:bg-white/10">Cancel</button>
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white">Confirm Conversion</button>
            </div>
        </form>
        </div>
    </dialog>

    <script>
        function syncConvertDestinationFields() {
            const type = document.getElementById('convert_destination_type').value;
            const bankField = document.getElementById('convert_bank_field');
            const walletField = document.getElementById('convert_wallet_field');
            const destinationInput = document.getElementById('convert_destination_id');

            bankField.classList.toggle('hidden', type !== 'bank');
            walletField.classList.toggle('hidden', type !== 'wallet');

            if (type === 'bank') {
                destinationInput.value = document.getElementById('convert_bank_id').value;
            } else if (type === 'wallet') {
                destinationInput.value = document.getElementById('convert_wallet_id').value;
            } else {
                destinationInput.value = '';
            }
        }

        document.getElementById('convert_destination_type').addEventListener('change', syncConvertDestinationFields);
        document.getElementById('convert_bank_id').addEventListener('change', syncConvertDestinationFields);
        document.getElementById('convert_wallet_id').addEventListener('change', syncConvertDestinationFields);
        syncConvertDestinationFields();

        @if($errors->any() && old('_token'))
            document.getElementById('convert-modal').showModal();
        @endif
    </script>
    @endif
@endsection
