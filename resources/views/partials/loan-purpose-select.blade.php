@php
    $selected = $selected ?? old('loan_purpose_id');
    $inputId = $inputId ?? 'loanPurposeId';
@endphp

<div class="{{ $wrapperClass ?? '' }}">
    <label for="{{ $inputId }}" class="{{ $labelClass ?? 'block text-sm font-medium text-slate-300 mb-2' }}">
        Loan Purpose <span class="text-rose-400">*</span>
    </label>
    <select id="{{ $inputId }}"
            name="loan_purpose_id"
            required
            class="{{ $selectClass ?? 'w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40' }}">
        <option value="">Select loan purpose</option>
        @foreach ($loanPurposes as $purpose)
            <option value="{{ $purpose->id }}" @selected((string) $selected === (string) $purpose->id)>
                {{ $purpose->name }}
            </option>
        @endforeach
    </select>
    @error('loan_purpose_id')
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>
