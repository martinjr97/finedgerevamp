@php
    $companyBadgeTone = match ($classification ?? '') {
        'MOU_REAL_EMPLOYER' => 'green',
        'GOVERNMENT_PRODUCT_PLACEHOLDER' => 'blue',
        'MARKETEER_PRODUCT_PLACEHOLDER' => 'purple',
        'CHARACTER_PRODUCT_PLACEHOLDER' => 'slate',
        'AMBIGUOUS_MANUAL_REVIEW', 'MANUAL_REVIEW' => 'amber',
        'UNUSED' => 'slate',
        default => 'slate',
    };
@endphp

@include('legacy.migration-dashboard.partials.badge', ['label' => $classification ?? 'UNKNOWN', 'tone' => $companyBadgeTone])
