@php
    use App\Support\PhoneNumberFormatter;

    $name = $name ?? 'phone';
    $label = $label ?? 'Mobile number';
    $value = $value ?? null;
    $required = $required ?? false;
    $inputClass = $inputClass ?? 'mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40';
    $labelClass = $labelClass ?? 'text-sm font-medium text-slate-200';
    $errorClass = $errorClass ?? 'mt-1 text-xs text-rose-400';
    $helpClass = $helpClass ?? 'mt-1 text-xs text-slate-400';
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
    $borderClass = $hasError ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-white/10';
@endphp

<div class="zambian-phone-field">
    <label for="{{ $name }}" class="{{ $labelClass }}">
        {{ $label }}
        @if ($required)
            <span class="text-rose-400">*</span>
        @endif
    </label>
    <input
        id="{{ $name }}"
        type="text"
        name="{{ $name }}"
        value="{{ $fieldValue }}"
        maxlength="12"
        inputmode="numeric"
        pattern="{{ PhoneNumberFormatter::HTML_PATTERN }}"
        placeholder="{{ PhoneNumberFormatter::PLACEHOLDER }}"
        @if ($required) required @endif
        aria-describedby="{{ $name }}-help @if($hasError){{ $name }}-error @endif"
        @if ($hasError) aria-invalid="true" @endif
        class="{{ trim($inputClass.' zambian-phone-input '.$borderClass) }}"
    />
    <p id="{{ $name }}-help" class="{{ $helpClass }}">
        {{ PhoneNumberFormatter::HELP_TEXT }}
        <span class="block text-slate-500">Example: {{ PhoneNumberFormatter::EXAMPLE_CONVERSION }}</span>
    </p>
    @error($name)
        <p id="{{ $name }}-error" class="{{ $errorClass }} font-medium" role="alert">{{ $message }}</p>
    @enderror
</div>
