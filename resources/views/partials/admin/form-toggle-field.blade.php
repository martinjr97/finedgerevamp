@php
    $checked = filter_var($checked ?? false, FILTER_VALIDATE_BOOLEAN);
    $disabled = (bool) ($disabled ?? false);
    $name = $name ?? '';
    $label = $label ?? '';
@endphp

<div class="space-y-1">
    @if ($label !== '')
        <span class="block text-sm font-medium text-primary">{{ $label }}</span>
    @endif

    <div class="flex items-center gap-3 {{ $disabled ? 'opacity-60' : '' }}">
        <input type="hidden" name="{{ $name }}" value="0">
        <input
            type="checkbox"
            id="{{ $inputId }}"
            name="{{ $name }}"
            value="1"
            class="gateway-toggle-input sr-only"
            @checked($checked)
            @disabled($disabled)
        >
        <label for="{{ $inputId }}" class="gateway-toggle gateway-toggle-track cursor-pointer">
            <span class="gateway-toggle-knob"></span>
        </label>
        <span class="text-xs font-bold uppercase tracking-wide {{ $checked ? 'gateway-toggle-label--on' : 'gateway-toggle-label--off' }}">
            {{ $checked ? 'On' : 'Off' }}
        </span>
    </div>
</div>
