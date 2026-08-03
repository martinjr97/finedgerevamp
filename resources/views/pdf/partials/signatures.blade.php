@php
    use App\Support\Pdf\DocumentPreparedBy;

    $generatedAt = $generatedAt ?? now()->timezone(config('app.timezone', 'Africa/Lusaka'));
    $customerName = $customerName ?? '__________________';
    $preparedBy = $preparedBy ?? DocumentPreparedBy::fromAuth();
    $includeSignatures = $includeSignatures ?? (bool) ($branding['include_signature_blocks'] ?? true);
@endphp

@if ($includeSignatures)
    <div class="signature-section">
        <table class="signature-table" role="presentation">
            <tr>
                <td class="signature-cell signature-left">
                    <div class="signature-label">Customer signature</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $customerName }}</div>
                    <div class="signature-meta">Date: ______________________</div>
                </td>
                <td class="signature-spacer"></td>
                <td class="signature-cell signature-right">
                    <div class="signature-label">Prepared / downloaded by</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $preparedBy['name'] ?: '__________________' }}</div>
                    @if (! empty($preparedBy['role']))
                        <div class="signature-meta">{{ $preparedBy['role'] }}</div>
                    @endif
                    <div class="signature-meta">{{ $generatedAt->format('d M Y, H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>
@endif
