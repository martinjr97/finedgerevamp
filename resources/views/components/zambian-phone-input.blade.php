@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'inputClass' => 'mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40',
    'labelClass' => 'text-sm font-medium text-slate-200',
    'errorClass' => 'mt-1 text-xs text-rose-400',
    'helpClass' => 'mt-1 text-xs text-slate-400',
    'showHelp' => true,
])

@php
    use App\Support\PhoneNumberFormatter;
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
    $borderClass = $hasError ? 'border-rose-500 ring-1 ring-rose-500/50' : 'border-white/10';
@endphp

<div {{ $attributes->class(['zambian-phone-field']) }}>
    @if ($label)
        <label for="{{ $name }}" class="{{ $labelClass }}">
            {{ $label }}
            @if ($required)
                <span class="text-rose-400">*</span>
            @endif
        </label>
    @endif
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
        aria-describedby="{{ $name }}-help @if($hasError) {{ $name }}-error @endif"
        @if ($hasError) aria-invalid="true" @endif
        class="{{ trim($inputClass.' zambian-phone-input '.$borderClass) }}"
    />
    @if ($showHelp)
        <p id="{{ $name }}-help" class="{{ $helpClass }}">
            {{ PhoneNumberFormatter::HELP_TEXT }}
            <span class="block text-slate-500">Example: {{ PhoneNumberFormatter::EXAMPLE_CONVERSION }}</span>
        </p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="{{ $errorClass }} font-medium" role="alert">{{ $message }}</p>
    @enderror
</div>
