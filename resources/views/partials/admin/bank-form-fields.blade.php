@php
    $bank = $bank ?? null;
    $selectedBankName = old('bank_name', $bank?->bank_name ?? '');
    $selectedBranch = old('branch', $bank?->branch ?? '');
    $institutionNames = $financialInstitutions->pluck('name');
    $bankNameInList = $selectedBankName !== '' && $institutionNames->contains($selectedBankName);
@endphp

<div class="rounded-2xl border border-cyan-400/20 bg-cyan-500/5 p-4 text-sm text-slate-300">
    <p class="font-medium text-cyan-200">Register a company bank account</p>
    <p class="mt-1 text-slate-400">
        This account is used when you disburse or receive funds from your own bank (treasury). Pick the institution and branch from the list, then enter the account details exactly as they appear on your statement.
    </p>
    @if($financialInstitutions->isEmpty())
        <p class="mt-3 text-amber-200">
            No financial institutions yet.
            <a href="{{ route('admin.financial-institutions.create') }}" class="underline hover:text-amber-100">Add an institution</a>
            and its branches before linking accounts here.
        </p>
    @endif
</div>

<div class="space-y-4">
    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Identification in the system</p>

    <div>
        <label class="text-sm font-medium text-slate-300" for="bank_account_label">
            Account label <span class="text-rose-400">*</span>
        </label>
        <p class="mt-1 text-xs text-slate-400">Short name shown in dropdowns when paying from or to this account.</p>
        <input type="text"
               id="bank_account_label"
               name="name"
               value="{{ old('name', $bank?->name) }}"
               required
               placeholder="e.g. Main Operations — Zanaco"
               class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
        @error('name')
            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="space-y-4 pt-2 border-t border-white/10">
    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Account at the bank</p>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-slate-300" for="bank_account_number">
                Account number <span class="text-rose-400">*</span>
            </label>
            <input type="text"
                   id="bank_account_number"
                   name="account_number"
                   value="{{ old('account_number', $bank?->account_number) }}"
                   required
                   placeholder="e.g. 1234567890"
                   class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            @error('account_number')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-sm font-medium text-slate-300" for="bank_account_name">
                Account name <span class="text-rose-400">*</span>
            </label>
            <p class="mt-1 text-xs text-slate-400">Registered holder name on the bank account.</p>
            <input type="text"
                   id="bank_account_name"
                   name="account_name"
                   value="{{ old('account_name', $bank?->account_name) }}"
                   required
                   placeholder="e.g. Loan Finance Limited"
                   class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            @error('account_name')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="space-y-4 pt-2 border-t border-white/10" data-bank-institution-fields>
    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Institution &amp; branch</p>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-slate-300" for="bank_financial_institution">
                Financial institution <span class="text-rose-400">*</span>
            </label>
            <select name="bank_name"
                    id="bank_financial_institution"
                    data-bank-institution-select
                    required
                    @disabled($financialInstitutions->isEmpty())
                    class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 disabled:opacity-50">
                <option value="">Select bank</option>
                @foreach($financialInstitutions as $institution)
                    <option value="{{ $institution->name }}" @selected($selectedBankName === $institution->name)>
                        {{ $institution->name }}@if($institution->code) ({{ $institution->code }})@endif
                    </option>
                @endforeach
                @if($selectedBankName !== '' && ! $bankNameInList)
                    <option value="{{ $selectedBankName }}" selected>{{ $selectedBankName }} (saved value)</option>
                @endif
            </select>
            @error('bank_name')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-sm font-medium text-slate-300" for="bank_branch">
                Branch
            </label>
            <select name="branch"
                    id="bank_branch"
                    data-bank-branch-select
                    @disabled($financialInstitutions->isEmpty())
                    class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 disabled:opacity-50">
                <option value="">Select branch (optional)</option>
                @foreach($financialInstitutions as $institution)
                    @foreach($institution->branches as $branch)
                        <option value="{{ $branch->name }}"
                                data-financial-institution-name="{{ $institution->name }}"
                                @selected($selectedBranch === $branch->name && $selectedBankName === $institution->name)>
                            {{ $branch->name }}@if($branch->code) — {{ $branch->code }}@endif
                        </option>
                    @endforeach
                @endforeach
                @if($selectedBranch !== '' && ! $financialInstitutions->flatMap->branches->contains('name', $selectedBranch))
                    <option value="{{ $selectedBranch }}" selected>{{ $selectedBranch }} (saved value)</option>
                @endif
            </select>
            <p class="mt-1 text-xs text-slate-400">Branches are filtered by the institution you select.</p>
            @error('branch')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="space-y-4 pt-2 border-t border-white/10">
    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Balance &amp; status</p>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium text-slate-300">Currency <span class="text-rose-400">*</span></label>
            <select name="currency" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                <option value="ZMW" @selected(old('currency', $bank?->currency ?? 'ZMW') === 'ZMW')>ZMW</option>
                <option value="USD" @selected(old('currency', $bank?->currency) === 'USD')>USD</option>
                <option value="EUR" @selected(old('currency', $bank?->currency) === 'EUR')>EUR</option>
            </select>
            @error('currency')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-sm font-medium text-slate-300">Opening balance <span class="text-rose-400">*</span></label>
            <input type="number"
                   name="opening_balance"
                   value="{{ old('opening_balance', $bank?->opening_balance ?? 0) }}"
                   step="0.01"
                   min="0"
                   required
                   placeholder="0.00"
                   class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            @error('opening_balance')
                <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $bank?->is_active ?? true))>
            Active
        </label>
    </div>

    <div>
        <label class="text-sm font-medium text-slate-300">Notes</label>
        <textarea name="notes"
                  rows="3"
                  placeholder="Optional internal note about this account"
                  class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes', $bank?->notes) }}</textarea>
        @error('notes')
            <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
        @enderror
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.initBankInstitutionFields = window.initBankInstitutionFields || function (root) {
                const scope = root || document;
                scope.querySelectorAll('[data-bank-institution-fields]').forEach((wrapper) => {
                    if (wrapper.dataset.bankInstitutionInitialized === '1') {
                        return;
                    }
                    wrapper.dataset.bankInstitutionInitialized = '1';

                    const institutionSelect = wrapper.querySelector('[data-bank-institution-select]');
                    const branchSelect = wrapper.querySelector('[data-bank-branch-select]');

                    if (!institutionSelect || !branchSelect) {
                        return;
                    }

                    const syncBranches = () => {
                        const institutionName = institutionSelect.value;
                        let hasVisible = false;

                        branchSelect.querySelectorAll('option[data-financial-institution-name]').forEach((option) => {
                            const matches = option.dataset.financialInstitutionName === institutionName;
                            option.hidden = !matches;
                            option.disabled = !matches;
                            if (matches) {
                                hasVisible = true;
                            }
                        });

                        const selected = branchSelect.selectedOptions[0];
                        if (!institutionName || !hasVisible) {
                            if (selected?.dataset?.financialInstitutionName) {
                                branchSelect.value = '';
                            }
                        } else if (selected?.disabled) {
                            branchSelect.value = '';
                        }
                    };

                    institutionSelect.addEventListener('change', syncBranches);
                    syncBranches();
                });
            };

            document.addEventListener('DOMContentLoaded', () => window.initBankInstitutionFields());
        </script>
    @endpush
@endonce
