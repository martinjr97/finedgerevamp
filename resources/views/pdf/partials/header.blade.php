@php
    /** @var array $branding */
    $documentDescriptor = $documentDescriptor ?? null;
    $documentReference = $documentReference ?? null;
    $generatedAt = $generatedAt ?? now();
@endphp
<table class="doc-header" role="presentation">
    <tr>
        <td class="logo-cell">
            @if (! empty($branding['logo_data_uri']))
                <img src="{{ $branding['logo_data_uri'] }}" alt="Logo" class="logo">
            @endif
        </td>
        <td>
            <div class="brand-name">{{ $branding['organization_name'] }}</div>
            @if ($documentDescriptor)
                <div class="brand-descriptor">{{ $documentDescriptor }}</div>
            @elseif (! empty($branding['tagline']))
                <div class="brand-descriptor">{{ $branding['tagline'] }}</div>
            @endif
        </td>
        <td class="meta-cell">
            @if ($documentReference)
                <div><span class="meta-strong">{{ $documentReference }}</span></div>
            @endif
            <div>Generated {{ $generatedAt->format('d M Y, H:i') }}</div>
            @if (! empty($branding['support_phone']))
                <div>Tel: {{ $branding['support_phone'] }}</div>
            @endif
            @if (! empty($branding['support_email']))
                <div>{{ $branding['support_email'] }}</div>
            @endif
            @if (! empty($branding['display_website']))
                <div>{{ $branding['display_website'] }}</div>
            @endif
        </td>
    </tr>
</table>
