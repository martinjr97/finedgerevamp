<div>
    <label class="text-sm font-medium text-slate-300" for="{{ $inputId ?? 'bank_account_digits' }}">
        Last account digits <span class="text-rose-400">*</span>
    </label>
    @if ($showHelp ?? true)
    @endif
    <input type="text"
           id="{{ $inputId ?? 'bank_account_digits' }}"
           name="account_number"
           value="{{ old('account_number', isset($bank) ? $bank->editableAccountDigits() : '') }}"
           required
           inputmode="numeric"
           pattern="[0-9]*"
           maxlength="6"
           minlength="2"
           placeholder="e.g. 7890"
           class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
    @error('account_number')
        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
    @enderror
</div>
