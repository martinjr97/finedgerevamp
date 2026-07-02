@php
    $name = $name ?? '';
    $checked = filter_var($checked ?? false, FILTER_VALIDATE_BOOLEAN);
    $disabled = (bool) ($disabled ?? false);
    $labelOn = $labelOn ?? 'On';
    $labelOff = $labelOff ?? 'Off';
@endphp

<div
    x-data="{ on: @json($checked) }"
    class="inline-flex items-center gap-3 {{ $disabled ? 'opacity-60 pointer-events-none' : '' }}"
>
    <input type="hidden" name="{{ $name }}" :value="on ? '1' : '0'">

    <button
        type="button"
        role="switch"
        :aria-checked="on"
        @unless($disabled)
            @click="on = !on"
        @endunless
        class="gateway-toggle"
        :class="on ? 'gateway-toggle--on' : 'gateway-toggle--off'"
    >
        <span class="gateway-toggle-knob" :class="on ? 'gateway-toggle-knob--on' : ''"></span>
    </button>

    <span
        class="min-w-[2rem] text-xs font-bold uppercase tracking-wide"
        :class="on ? 'gateway-toggle-label--on' : 'gateway-toggle-label--off'"
        x-text="on ? @js($labelOn) : @js($labelOff)"
    ></span>
</div>
