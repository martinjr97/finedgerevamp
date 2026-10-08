<div
    x-show="paymentModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="paymentModalTitle"
    @keydown.escape.window="paymentModalOpen = false"
>
    <div
        class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl"
        @click.outside="paymentModalOpen = false"
    >
        <div class="flex items-start justify-between gap-3 mb-1">
            <div>
                <h3 id="paymentModalTitle" class="text-lg font-semibold text-white">Add payment account</h3>
                <p class="mt-1 text-sm text-slate-400">
                    Add a bank account or mobile money wallet for {{ $employee->full_name }}’s payroll.
                </p>
            </div>
            <button type="button" @click="paymentModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:bg-white/10 hover:text-white" aria-label="Close">✕</button>
        </div>

        @php
            $primaryAccount = $employee->bankAccounts->firstWhere('is_primary', true);
        @endphp
        @if ($primaryAccount)
            <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <span class="text-slate-400">Primary on file:</span>
                <span class="ml-1 font-medium text-white capitalize">{{ str_replace('_', ' ', $primaryAccount->account_type ?? 'bank') }}</span>
                <span class="text-slate-500">· {{ $primaryAccount->account_name }} · {{ $primaryAccount->maskedAccountNumber() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.hr.employees.bank.store', $employee) }}" class="mt-5 space-y-4" novalidate>
            @csrf

            <div class="flex flex-wrap gap-3">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
                       :class="payoutMethod === 'bank' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
                    <input type="radio" name="payout_method" value="bank" x-model="payoutMethod" class="sr-only"> Bank account
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
                       :class="payoutMethod === 'mobile_money' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
                    <input type="radio" name="payout_method" value="mobile_money" x-model="payoutMethod" class="sr-only"> Mobile money
                </label>
            </div>

            <div x-show="payoutMethod === 'bank'" x-cloak class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="payment_financial_institution_id" class="text-sm font-medium text-slate-200">Bank / institution</label>
                    <select
                        id="payment_financial_institution_id"
                        name="financial_institution_id"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('financial_institution_id') border-rose-400 @enderror"
                    >
                        <option value="">Other / type name below</option>
                        @foreach ($financialInstitutions as $inst)
                            <option value="{{ $inst->id }}" @selected((int) old('financial_institution_id') === $inst->id)>{{ $inst->name }}</option>
                        @endforeach
                    </select>
                    @error('financial_institution_id')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="payment_bank_name" class="text-sm font-medium text-slate-200">Bank name (if not in list)</label>
                    <input
                        id="payment_bank_name"
                        type="text"
                        name="bank_name"
                        value="{{ old('bank_name') }}"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('bank_name') border-rose-400 @enderror"
                    >
                    @error('bank_name')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="payment_branch_name" class="text-sm font-medium text-slate-200">Branch name</label>
                    <input id="payment_branch_name" type="text" name="branch_name" value="{{ old('branch_name') }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
                <div>
                    <label for="payment_branch_code" class="text-sm font-medium text-slate-200">Branch code</label>
                    <input id="payment_branch_code" type="text" name="branch_code" value="{{ old('branch_code') }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
                <div>
                    <label for="payment_account_name" class="text-sm font-medium text-slate-200">
                        Account name <span class="text-rose-400">*</span>
                    </label>
                    <input
                        id="payment_account_name"
                        type="text"
                        name="account_name"
                        value="{{ old('account_name') }}"
                        :required="payoutMethod === 'bank'"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('account_name') border-rose-400 @enderror"
                    >
                    @error('account_name')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="payment_account_number" class="text-sm font-medium text-slate-200">
                        Account number <span class="text-rose-400">*</span>
                    </label>
                    <input
                        id="payment_account_number"
                        type="text"
                        name="account_number"
                        value="{{ old('account_number') }}"
                        :required="payoutMethod === 'bank'"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 font-mono text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('account_number') border-rose-400 @enderror"
                    >
                    @error('account_number')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <input type="hidden" name="account_type" value="bank">
            </div>

            <div x-show="payoutMethod === 'mobile_money'" x-cloak class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="payment_mobile_provider" class="text-sm font-medium text-slate-200">
                        Mobile money provider <span class="text-rose-400">*</span>
                    </label>
                    <select
                        id="payment_mobile_provider"
                        name="mobile_money_provider"
                        :required="payoutMethod === 'mobile_money'"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('mobile_money_provider') border-rose-400 @enderror"
                    >
                        <option value="">Select provider</option>
                        @foreach ($mobileMoneyProviders as $provider)
                            <option value="{{ $provider }}" @selected(old('mobile_money_provider') === $provider)>{{ $provider }}</option>
                        @endforeach
                    </select>
                    @error('mobile_money_provider')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="payment_mobile_account_name" class="text-sm font-medium text-slate-200">
                        Registered name <span class="text-rose-400">*</span>
                    </label>
                    <input
                        id="payment_mobile_account_name"
                        type="text"
                        name="mobile_account_name"
                        value="{{ old('mobile_account_name') }}"
                        :required="payoutMethod === 'mobile_money'"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('mobile_account_name') border-rose-400 @enderror"
                    >
                    @error('mobile_account_name')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="payment_mobile_number" class="text-sm font-medium text-slate-200">
                        Mobile number <span class="text-rose-400">*</span>
                    </label>
                    <input
                        id="payment_mobile_number"
                        type="text"
                        name="mobile_number"
                        value="{{ old('mobile_number') }}"
                        placeholder="e.g. 0971234567"
                        :required="payoutMethod === 'mobile_money'"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40 @error('mobile_number') border-rose-400 @enderror"
                    >
                    @error('mobile_number')
                        <p class="mt-1 text-xs text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input
                    type="checkbox"
                    name="is_primary"
                    value="1"
                    @checked(old('is_primary'))
                    class="rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-400/40"
                >
                Set as primary payroll account
            </label>

            <div class="flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                <button type="button" @click="paymentModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                    Cancel
                </button>
                <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">
                    Save payment account
                </button>
            </div>
        </form>
    </div>
</div>
