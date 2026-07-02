@extends('layouts.admin')

@section('title', 'Edit '.$gateway->name)

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit '.$gateway->name,
            'description' => 'Update operational settings only. Gateway code, provider, and credentials cannot be changed here.',
            'buttons' => [
                [
                    'action' => 'view',
                    'text' => 'View Gateway',
                    'href' => route('admin.payment-gateways.show', $gateway),
                ],
            ],
        ])

        @if (! $gateway->hasLinkedFinancialAccount())
            <div class="rounded-2xl border border-amber-400/50 bg-amber-500/10 px-5 py-4 text-sm text-amber-100 max-w-3xl">
                <p class="font-semibold text-amber-50">This gateway has no linked financial account.</p>
                <p class="mt-1 text-amber-100/90">Automated finance posting will not occur until an account is configured. Manual processing remains available.</p>
            </div>
        @endif

        <form action="{{ route('admin.payment-gateways.update', $gateway) }}" method="POST" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6 max-w-3xl">
            @csrf
            @method('PUT')

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="block text-sm text-white/70 mb-2">Status</label>
                    <select name="status" class="w-full rounded-xl border border-white/20 bg-white/5 px-4 py-3 text-white">
                        @foreach (\App\PaymentPlatform\Enums\PaymentGatewayStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $gateway->status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm text-white/70 mb-2">Priority (legacy — not used for routing)</label>
                    <input type="number" name="priority" value="{{ old('priority', $gateway->priority) }}" min="1" max="9999" class="w-full rounded-xl border border-white/20 bg-white/5 px-4 py-3 text-white">
                    <p class="mt-1 text-xs text-slate-500">Use case routing is configured under <a href="{{ route('admin.payment-gateway-routing.index') }}" class="text-cyan-300 hover:text-cyan-200">Gateway Routing</a>.</p>
                    @error('priority')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-2 text-sm text-slate-300">
                <p class="font-semibold text-white">Gateway status guide</p>
                @foreach ($statusDescriptions as $value => $description)
                    <p><span class="font-medium text-white capitalize">{{ $value }}:</span> {{ $description }}</p>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default', $gateway->is_default)) class="rounded">
                <label for="is_default" class="text-white">Default gateway for collections (legacy — routing uses Gateway Routing instead)</label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-center gap-3">
                    <input type="hidden" name="supports_collections" value="0">
                    <input type="checkbox" name="supports_collections" value="1" id="supports_collections" @checked(old('supports_collections', $gateway->supports_collections)) class="rounded">
                    <label for="supports_collections" class="text-white">Collections enabled</label>
                </div>
                <div class="flex items-center gap-3">
                    <input type="hidden" name="supports_disbursements" value="0">
                    <input type="checkbox" name="supports_disbursements" value="1" id="supports_disbursements" @checked(old('supports_disbursements', $gateway->supports_disbursements)) class="rounded">
                    <label for="supports_disbursements" class="text-white">Disbursements enabled</label>
                </div>
            </div>

            <div class="border-t border-white/10 pt-6 space-y-4">
                <h3 class="text-lg font-semibold text-white">Linked Financial Account</h3>
                <p class="text-sm text-slate-400">Select an existing bank or wallet account. New accounts cannot be created from this page.</p>

                <div>
                    <label class="block text-sm text-white/70 mb-2">Financial Account Type</label>
                    <select name="financial_account_type" id="financial_account_type" class="w-full rounded-xl border border-white/20 bg-white/5 px-4 py-3 text-white">
                        <option value="">— None —</option>
                        <option value="bank" @selected(old('financial_account_type', $gateway->financial_account_type?->value) === 'bank')>Bank</option>
                        <option value="wallet" @selected(old('financial_account_type', $gateway->financial_account_type?->value) === 'wallet')>Wallet</option>
                    </select>
                </div>

                <div id="bank_account_field" class="hidden">
                    <label class="block text-sm text-white/70 mb-2">Bank Account</label>
                    <select id="financial_account_id_bank" class="w-full rounded-xl border border-white/20 bg-white/5 px-4 py-3 text-white">
                        <option value="">Select bank</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank->id }}" @selected(
                                old('financial_account_type', $gateway->financial_account_type?->value) === 'bank'
                                && (int) old('financial_account_id', $gateway->financial_account_id) === $bank->id
                            )>{{ $bank->name }} (ZMW {{ number_format($bank->current_balance, 2) }})</option>
                        @endforeach
                    </select>
                </div>

                <div id="wallet_account_field" class="hidden">
                    <label class="block text-sm text-white/70 mb-2">Wallet Account</label>
                    <select id="financial_account_id_wallet" class="w-full rounded-xl border border-white/20 bg-white/5 px-4 py-3 text-white">
                        <option value="">Select wallet</option>
                        @foreach ($wallets as $wallet)
                            <option value="{{ $wallet->id }}" @selected(
                                old('financial_account_type', $gateway->financial_account_type?->value) === 'wallet'
                                && (int) old('financial_account_id', $gateway->financial_account_id) === $wallet->id
                            )>{{ $wallet->name }} (ZMW {{ number_format($wallet->current_balance, 2) }})</option>
                        @endforeach
                    </select>
                </div>

                <input type="hidden" name="financial_account_id" id="financial_account_id" value="{{ old('financial_account_id', $gateway->financial_account_id) }}">

                @if ($linkedBalance !== null)
                    <p class="text-sm text-slate-400">Current linked balance: ZMW {{ number_format($linkedBalance, 2) }} (read-only)</p>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-2 text-sm text-slate-400">
                <p class="font-semibold text-slate-300">Read-only gateway identity</p>
                <p><span class="text-white/70">Code:</span> {{ $gateway->code }}</p>
                <p><span class="text-white/70">Provider:</span> {{ \App\Support\PaymentGatewayAdminUi::providerDisplayName($gateway) }}</p>
            </div>

            <div class="flex flex-wrap gap-4">
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-500">Save Changes</button>
                <a href="{{ route('admin.payment-gateways.show', $gateway) }}" class="rounded-xl border border-white/20 px-6 py-3 text-slate-300 hover:bg-white/5">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const typeSelect = document.getElementById('financial_account_type');
            const bankField = document.getElementById('bank_account_field');
            const walletField = document.getElementById('wallet_account_field');
            const bankSelect = document.getElementById('financial_account_id_bank');
            const walletSelect = document.getElementById('financial_account_id_wallet');
            const hiddenId = document.getElementById('financial_account_id');

            function sync() {
                const type = typeSelect.value;

                bankField.classList.toggle('hidden', type !== 'bank');
                walletField.classList.toggle('hidden', type !== 'wallet');

                bankSelect.disabled = type !== 'bank';
                walletSelect.disabled = type !== 'wallet';

                if (type === 'bank') {
                    hiddenId.value = bankSelect.value;
                } else if (type === 'wallet') {
                    hiddenId.value = walletSelect.value;
                } else {
                    hiddenId.value = '';
                }
            }

            typeSelect.addEventListener('change', sync);
            bankSelect.addEventListener('change', sync);
            walletSelect.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
