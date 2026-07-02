@php
    $indicator = \App\Support\PaymentGatewayAdminUi::statusIndicator($status);
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $indicator['class'] }}">
    <span aria-hidden="true">{{ $indicator['emoji'] }}</span>
    {{ $indicator['label'] }}
</span>
