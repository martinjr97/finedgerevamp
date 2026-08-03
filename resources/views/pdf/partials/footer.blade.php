@php
    /** @var array $branding */
    $generatedAt = $generatedAt ?? now();
    $generatedBy = $generatedBy ?? null;
    $disclaimer = $disclaimer ?? null;
@endphp
<div class="document-certification">
    @if (filled($disclaimer))
        <div class="cert-line">{{ $disclaimer }}</div>
    @endif
    <div class="cert-line">
        Generated {{ $generatedAt->format('d M Y, H:i') }}
        @if ($generatedBy)
            · By {{ $generatedBy }}
        @endif
        · {{ $branding['organization_name'] }}
    </div>
    @if (! empty($branding['support_email']) || ! empty($branding['support_phone']))
        <div class="cert-line">
            Support:
            @if (! empty($branding['support_email']))
                {{ $branding['support_email'] }}
            @endif
            @if (! empty($branding['support_email']) && ! empty($branding['support_phone']))
                ·
            @endif
            @if (! empty($branding['support_phone']))
                {{ $branding['support_phone'] }}
            @endif
        </div>
    @endif
</div>
