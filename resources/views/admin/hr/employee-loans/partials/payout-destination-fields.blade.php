@php
    $formDefaults = $formDefaults ?? [];
    $inputClass = 'mt-1 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2';
@endphp

<div class="md:col-span-2 rounded-2xl border border-white/10 bg-white/[0.03] p-4 space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Payment details</h3>
        <p class="text-xs text-slate-400 mt-1">Where loan funds should be sent when the loan is disbursed. Choose an account linked to the employee or enter an alternative.</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
               :class="payoutDestinationMode === 'employee_account' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
            <input type="radio" name="payout_destination_mode" value="employee_account" x-model="payoutDestinationMode" class="sr-only" :disabled="!employeeAccounts.length">
            Use employee account on file
        </label>
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
               :class="payoutDestinationMode === 'alternative' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
            <input type="radio" name="payout_destination_mode" value="alternative" x-model="payoutDestinationMode" class="sr-only">
            Alternative account
        </label>
    </div>

    <p class="text-xs text-amber-300/90" x-show="employeeId && !employeeAccountsLoading && !employeeAccounts.length">
        This employee has no payment accounts on file. Add one on their HR profile or use an alternative account below.
    </p>
    <p class="text-xs text-slate-500" x-show="employeeAccountsLoading">Loading employee payment accounts…</p>

    <div x-show="payoutDestinationMode === 'employee_account'" x-cloak class="space-y-2">
        <label class="text-sm text-slate-300">Employee payment account <span class="text-rose-400">*</span></label>
        <select name="payout_employee_bank_account_id"
                class="{{ $inputClass }}"
                x-model="selectedEmployeeAccountId"
                :required="payoutDestinationMode === 'employee_account'">
            <option value="">Select account</option>
            <template x-for="account in employeeAccounts" :key="account.id">
                <option :value="account.id" x-text="account.label"></option>
            </template>
        </select>
        @error('payout_employee_bank_account_id')
            <p class="text-xs text-rose-400">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="payoutDestinationMode === 'alternative'" x-cloak class="space-y-4">
        <div class="flex flex-wrap gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-1.5 text-sm"
                   :class="payoutMethod === 'bank' ? 'border-cyan-400/60 text-cyan-100' : 'border-white/10 text-slate-400'">
                <input type="radio" name="payout_method" value="bank" x-model="payoutMethod" class="sr-only"> Bank account
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-1.5 text-sm"
                   :class="payoutMethod === 'mobile_money' ? 'border-cyan-400/60 text-cyan-100' : 'border-white/10 text-slate-400'">
                <input type="radio" name="payout_method" value="mobile_money" x-model="payoutMethod" class="sr-only"> Mobile money
            </label>
        </div>

        <div x-show="payoutMethod === 'bank'" class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-slate-300">Bank / institution</label>
                @php $pfi = old('payout_financial_institution_id', $formDefaults['payout_financial_institution_id'] ?? ''); @endphp
                <select name="payout_financial_institution_id" class="{{ $inputClass }}">
                    <option value="">Other / type name below</option>
                    @foreach ($financialInstitutions as $inst)
                        <option value="{{ $inst->id }}" @selected((string) $pfi === (string) $inst->id)>{{ $inst->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-300">Bank name (if not in list)</label>
                <input type="text" name="payout_bank_name" value="{{ old('payout_bank_name', $formDefaults['payout_bank_name'] ?? '') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="text-sm text-slate-300">Branch name</label>
                <input type="text" name="payout_branch_name" value="{{ old('payout_branch_name', $formDefaults['payout_branch_name'] ?? '') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="text-sm text-slate-300">Account name <span class="text-rose-400">*</span></label>
                <input type="text" name="payout_account_name" value="{{ old('payout_account_name', $formDefaults['payout_account_name'] ?? '') }}" class="{{ $inputClass }}" :required="payoutDestinationMode === 'alternative' && payoutMethod === 'bank'">
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-300">Account number <span class="text-rose-400">*</span></label>
                <input type="text" name="payout_account_number" value="{{ old('payout_account_number', $formDefaults['payout_account_number'] ?? '') }}" class="{{ $inputClass }} font-mono" :required="payoutDestinationMode === 'alternative' && payoutMethod === 'bank'">
            </div>
        </div>

        <div x-show="payoutMethod === 'mobile_money'" class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-slate-300">Mobile money provider <span class="text-rose-400">*</span></label>
                <select name="payout_mobile_money_provider" class="{{ $inputClass }}" :required="payoutDestinationMode === 'alternative' && payoutMethod === 'mobile_money'">
                    <option value="">Select provider</option>
                    @foreach ($mobileMoneyProviders as $provider)
                        <option value="{{ $provider }}" @selected(old('payout_mobile_money_provider', $formDefaults['payout_mobile_money_provider'] ?? '') === $provider)>{{ $provider }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-300">Registered name <span class="text-rose-400">*</span></label>
                <input type="text" name="payout_mobile_account_name" value="{{ old('payout_mobile_account_name', $formDefaults['payout_mobile_account_name'] ?? '') }}" class="{{ $inputClass }}" :required="payoutDestinationMode === 'alternative' && payoutMethod === 'mobile_money'">
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-300">Mobile number <span class="text-rose-400">*</span></label>
                <input type="text" name="payout_mobile_number" value="{{ old('payout_mobile_number', $formDefaults['payout_mobile_number'] ?? '') }}" class="{{ $inputClass }}" :required="payoutDestinationMode === 'alternative' && payoutMethod === 'mobile_money'">
            </div>
        </div>
    </div>

    @error('payout_destination_mode')
        <p class="text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>
