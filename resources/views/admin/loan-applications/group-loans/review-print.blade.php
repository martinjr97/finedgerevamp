<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Loan Review Document | {{ config('app.system_name') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 16mm 14mm 18mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #0f172a;
        }

        .document {
            position: relative;
        }

        .watermark-layer {
            position: fixed;
            top: 28%;
            left: 0;
            right: 0;
            text-align: center;
            z-index: -1;
            pointer-events: none;
        }

        .watermark-logo {
            width: 128mm;
            max-width: 86%;
            opacity: 0.05;
            filter: grayscale(100%);
        }

        .watermark-logo-fallback {
            display: inline-block;
            font-size: 110pt;
            font-weight: 700;
            color: #475569;
            opacity: 0.055;
            line-height: 1;
            text-transform: uppercase;
        }

        .watermark-text {
            margin-top: 8mm;
            font-size: 44pt;
            font-weight: 700;
            letter-spacing: 9pt;
            color: #475569;
            opacity: 0.07;
            transform: rotate(-26deg);
            transform-origin: center;
            text-transform: uppercase;
        }

        .doc-header {
            border-bottom: 1.4pt solid #1d4ed8;
            padding-bottom: 7mm;
            margin-bottom: 6mm;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-logo-cell {
            width: 24mm;
            padding-right: 4mm;
        }

        .header-logo-wrap {
            width: 20mm;
            height: 20mm;
            border: 1pt solid #cbd5e1;
            border-radius: 3mm;
            background: #ffffff;
            text-align: center;
            line-height: 18mm;
            overflow: hidden;
        }

        .header-logo {
            max-width: 18mm;
            max-height: 18mm;
            vertical-align: middle;
        }

        .header-logo-fallback {
            font-size: 16pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 20mm;
            display: inline-block;
        }

        .org-name {
            font-size: 14pt;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
            letter-spacing: 0.4pt;
        }

        .org-tagline {
            margin: 1.5mm 0 0;
            font-size: 8.6pt;
            color: #475569;
        }

        .org-address {
            margin-top: 2mm;
            font-size: 8.6pt;
            color: #334155;
            line-height: 1.35;
        }

        .header-contact-cell {
            width: 58mm;
            border-left: 1pt solid #e2e8f0;
            padding-left: 4mm;
            text-align: right;
        }

        .contact-label {
            margin: 0 0 2mm;
            font-size: 7.2pt;
            text-transform: uppercase;
            letter-spacing: 1pt;
            color: #64748b;
            font-weight: 700;
        }

        .contact-line {
            margin: 0;
            font-size: 8.5pt;
            color: #334155;
            line-height: 1.35;
            word-break: break-word;
        }

        .doc-title-row {
            margin-top: 5mm;
            border: 1pt solid #dbeafe;
            background: #eff6ff;
            border-radius: 3mm;
            padding: 3mm 4mm;
        }

        .doc-title {
            margin: 0;
            font-size: 16pt;
            letter-spacing: 0.8pt;
            font-weight: 700;
            color: #0f172a;
        }

        .doc-subtitle {
            margin: 1.2mm 0 0;
            font-size: 8.5pt;
            color: #334155;
        }

        .doc-identification {
            margin-top: 3.5mm;
            width: 100%;
            border-collapse: collapse;
        }

        .doc-identification td {
            width: 25%;
            padding: 2mm 2.4mm;
            border: 1pt solid #e2e8f0;
            vertical-align: top;
            background: #ffffff;
        }

        .label {
            display: block;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #64748b;
            margin-bottom: 0.8mm;
        }

        .value {
            display: block;
            font-size: 9pt;
            color: #0f172a;
            font-weight: 600;
            line-height: 1.3;
        }

        .section {
            margin-bottom: 6mm;
        }

        .section-title {
            margin: 0 0 2.4mm;
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.9pt;
            color: #1e3a8a;
            border-bottom: 1pt solid #cbd5e1;
            padding-bottom: 1.2mm;
        }

        .summary-table,
        .rates-table,
        .members-table,
        .totals-table,
        .installment-members-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            border: 1pt solid #e2e8f0;
            padding: 2.4mm 2.8mm;
            vertical-align: top;
            width: 50%;
        }

        .summary-table td .value {
            font-size: 9.5pt;
        }

        .rates-table th,
        .members-table th,
        .totals-table th,
        .installment-members-table th {
            background: #f8fafc;
            border: 1pt solid #d1d5db;
            padding: 2.2mm 2.4mm;
            font-size: 7.8pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #1e293b;
            text-align: left;
        }

        .rates-table td,
        .members-table td,
        .totals-table td,
        .installment-members-table td {
            border: 1pt solid #e2e8f0;
            padding: 2.4mm 2.6mm;
            font-size: 8.7pt;
            vertical-align: top;
        }

        .members-table tbody tr:nth-child(even),
        .installment-members-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .money {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-weight: 600;
        }

        .meta-muted {
            color: #64748b;
            font-size: 8pt;
        }

        .terms-block {
            border: 1pt solid #d1d5db;
            background: #f8fafc;
            border-radius: 2.2mm;
            padding: 3.2mm 3.4mm;
            font-size: 8.8pt;
            white-space: pre-line;
            color: #1f2937;
        }

        .schedule-intro {
            margin: 0 0 2mm;
            font-size: 8.6pt;
            color: #334155;
        }

        .installment-card {
            border: 1pt solid #cbd5e1;
            border-radius: 2.2mm;
            margin-bottom: 3.6mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }

        .installment-card-header {
            background: #f1f5f9;
            border-bottom: 1pt solid #cbd5e1;
            padding: 2.6mm 2.8mm;
        }

        .installment-header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .installment-header-table td {
            width: 33.333%;
            vertical-align: top;
        }

        .installment-header-table .value {
            font-size: 9.2pt;
        }

        .installment-body {
            padding: 2.6mm 2.8mm 3mm;
        }

        .installment-subtitle {
            margin: 0 0 1.8mm;
            font-size: 8.3pt;
            color: #1e293b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }

        .totals-table th {
            width: 72%;
        }

        .totals-table td {
            width: 28%;
            text-align: right;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .signature-section {
            margin-top: 8mm;
            page-break-inside: avoid;
        }

        .signature-note {
            margin: 0 0 2.6mm;
            font-size: 8.4pt;
            color: #334155;
        }

        .signature-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4mm 6mm;
            margin: 0 -4mm;
        }

        .signature-grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 4mm;
        }

        .signature-line {
            border-bottom: 1pt solid #64748b;
            height: 8mm;
            margin-bottom: 1.5mm;
        }

        .signature-role {
            font-size: 8.2pt;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }

        .signature-meta {
            margin-top: 0.8mm;
            font-size: 7.8pt;
            color: #64748b;
        }

        .footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            border-top: 1pt solid #cbd5e1;
            padding-top: 2.4mm;
            font-size: 7.7pt;
            color: #475569;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        .page-counter::after {
            content: "Page " counter(page) " of " counter(pages);
        }

        .page-break-before {
            page-break-before: always;
        }
    </style>
</head>
<body>
@php
    $memberIds = collect($wizard['member_ids'] ?? [])->map(fn ($id) => (int) $id);
    $totals = $wizard['totals'] ?? [];
    $branding = $printBranding ?? [];
    $logoDataUri = $branding['logo_data_uri'] ?? null;
    $logoUrl = $branding['logo_url'] ?? null;
    $generatedAtValue = $generatedAt ?? now();
    $documentRef = 'GLR-DRAFT-'.str_pad((string) $loanProduct->id, 4, '0', STR_PAD_LEFT).'-'.$generatedAtValue->format('YmdHis');
@endphp

<div class="document">
    <div class="watermark-layer" aria-hidden="true">
        @if ($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="" class="watermark-logo">
        @elseif ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="watermark-logo">
        @else
            <span class="watermark-logo-fallback">{{ strtoupper(substr((string) ($branding['organization_name'] ?? config('app.system_name')), 0, 1)) }}</span>
        @endif
        <div class="watermark-text">Confidential</div>
    </div>

    <header class="doc-header section">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    <div class="header-logo-wrap">
                        @if ($logoDataUri)
                            <img src="{{ $logoDataUri }}" alt="Company Logo" class="header-logo">
                        @elseif ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Company Logo" class="header-logo">
                        @else
                            <span class="header-logo-fallback">{{ strtoupper(substr((string) ($branding['organization_name'] ?? config('app.system_name')), 0, 1)) }}</span>
                        @endif
                    </div>
                </td>
                <td>
                    <h1 class="org-name">{{ $branding['organization_name'] ?? config('app.system_name') }}</h1>
                    <p class="org-tagline">{{ $branding['tagline'] ?? config('app.system_tagline') }}</p>
                    <div class="org-address">
                        @forelse ((array) ($branding['address_lines'] ?? []) as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <div>{{ config('app.support_address_line1', 'Customer Support Office') }}</div>
                            <div>{{ collect([config('app.support_city'), config('app.support_country')])->filter()->implode(', ') }}</div>
                        @endforelse
                    </div>
                </td>
                <td class="header-contact-cell">
                    <p class="contact-label">Official Contact</p>
                    <p class="contact-line">Tel: {{ $branding['contact_phone'] ?? config('app.support_phone', 'N/A') }}</p>
                    <p class="contact-line">Email: {{ $branding['contact_email'] ?? config('app.support_email', 'N/A') }}</p>
                    @if (!empty($branding['display_website']))
                        <p class="contact-line">Web: {{ $branding['display_website'] }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <div class="doc-title-row">
            <h2 class="doc-title">Group Loan Review Document</h2>
            <p class="doc-subtitle">Document Classification: <strong>CONFIDENTIAL</strong></p>
        </div>

        <table class="doc-identification">
            <tr>
                <td>
                    <span class="label">Document Reference</span>
                    <span class="value">{{ $documentRef }}</span>
                </td>
                <td>
                    <span class="label">Generated On</span>
                    <span class="value">{{ $generatedAtValue->format('d M Y, H:i') }}</span>
                </td>
                <td>
                    <span class="label">Prepared By</span>
                    <span class="value">{{ $printedBy?->full_name ?? 'System' }}</span>
                </td>
                <td>
                    <span class="label">Installments</span>
                    <span class="value">{{ count($repaymentSchedule) }}</span>
                </td>
            </tr>
        </table>
    </header>

    <section class="section">
        <h3 class="section-title">1. Loan Summary</h3>
        <table class="summary-table">
            <tr>
                <td>
                    <span class="label">Product</span>
                    <span class="value">{{ $loanProduct->name }}</span>
                </td>
                <td>
                    <span class="label">Group Name</span>
                    <span class="value">{{ $group?->name ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Group Loan Name</span>
                    <span class="value">{{ $wizard['loan_name'] ?? 'N/A' }}</span>
                </td>
                <td>
                    <span class="label">Relationship Manager</span>
                    <span class="value">{{ $relationshipManager?->full_name ?? 'Unassigned' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Repayment Structure</span>
                    <span class="value">{{ ucfirst($wizard['repayment_structure'] ?? 'N/A') }}</span>
                </td>
                <td>
                    <span class="label">Loan Timeline</span>
                    <span class="value">
                        Start: {{ !empty($wizard['start_date']) ? \Carbon\Carbon::parse($wizard['start_date'])->format('d M Y') : 'N/A' }}
                        | Due: {{ !empty($wizard['due_date']) ? \Carbon\Carbon::parse($wizard['due_date'])->format('d M Y') : 'N/A' }}
                    </span>
                </td>
            </tr>
        </table>
    </section>

    <section class="section">
        <h3 class="section-title">2. Group Member Details</h3>
        <table class="members-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Customer</th>
                    <th style="width: 13%;">Group Title</th>
                    <th style="width: 14%;">Principal Amount</th>
                    <th style="width: 14%;">Projected Repayment</th>
                    <th style="width: 17%;">Expected Installment (Per Member)</th>
                    <th style="width: 20%;">Disbursement Account</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($memberIds as $memberId)
                    @php
                        $member = $members->get($memberId);
                        $titleId = (int) data_get($wizard, "member_titles.$memberId");
                        $title = $titles->get($titleId);
                        $calc = data_get($wizard, "member_calculations.$memberId", []);
                        $memberSchedule = (array) data_get($memberInstallmentSchedules, $memberId, []);
                        $memberExpectedInstallment = (float) ($memberSchedule[1] ?? 0);
                        $memberFinalInstallment = (float) ($memberSchedule[$installmentCount] ?? $memberExpectedInstallment);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $member?->full_name ?? 'Unknown Customer' }}</strong><br>
                            <span class="meta-muted">{{ $member?->phone ?: 'No phone' }}</span>
                        </td>
                        <td>{{ $title?->name ?? 'N/A' }}</td>
                        <td class="money">ZMW {{ number_format((float) data_get($calc, 'principal_amount', 0), 2) }}</td>
                        <td class="money">ZMW {{ number_format((float) data_get($calc, 'total_repayment_amount', 0), 2) }}</td>
                        <td class="money">
                            ZMW {{ number_format($memberExpectedInstallment, 2) }}
                            @if ($installmentCount > 1 && abs($memberFinalInstallment - $memberExpectedInstallment) > 0.009)
                                <br><span class="meta-muted">Last: ZMW {{ number_format($memberFinalInstallment, 2) }}</span>
                            @endif
                        </td>
                        <td>{{ $member?->phone ?: 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="section">
        <h3 class="section-title">3. Rates and Charges</h3>
        <table class="rates-table">
            <thead>
                <tr>
                    <th style="width: 33.33%;">Processing Fee (%)</th>
                    <th style="width: 33.33%;">Interest Rate for Full Period (%)</th>
                    <th style="width: 33.33%;">Arrears Rate (%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="money">{{ number_format((float) ($wizard['processing_fee_percentage'] ?? 0), 4) }}%</td>
                    <td class="money">{{ number_format((float) ($wizard['monthly_interest_rate'] ?? 0), 4) }}%</td>
                    <td class="money">{{ number_format((float) ($wizard['arrears_rate'] ?? 0), 4) }}%</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="section page-break-before">
        <h3 class="section-title">4. Repayment Schedule</h3>
        <p class="schedule-intro">
            Full installment trail for audit and review. Each installment shows the group expected amount and the individual expected repayment contribution per selected member.
        </p>

        @forelse ($repaymentSchedule as $scheduleItem)
            @php
                $memberBreakdown = (array) ($scheduleItem['member_breakdown'] ?? []);
            @endphp
            <article class="installment-card">
                <div class="installment-card-header">
                    <table class="installment-header-table">
                        <tr>
                            <td>
                                <span class="label">Installment #</span>
                                <span class="value">{{ $scheduleItem['period_number'] }}</span>
                            </td>
                            <td>
                                <span class="label">Due Date</span>
                                <span class="value">{{ $scheduleItem['due_date']->format('d M Y') }}</span>
                            </td>
                            <td style="text-align: right;">
                                <span class="label">Group Expected Amount</span>
                                <span class="value">ZMW {{ number_format((float) $scheduleItem['expected_amount'], 2) }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="installment-body">
                    <p class="installment-subtitle">Individual Expected Repayment Trail</p>
                    <table class="installment-members-table">
                        <thead>
                            <tr>
                                <th style="width: 70%;">Member</th>
                                <th style="width: 30%;">Expected Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($memberBreakdown === [])
                                <tr>
                                    <td colspan="2">No per-member breakdown available.</td>
                                </tr>
                            @else
                                @foreach ($memberIds as $memberId)
                                    <tr>
                                        <td>{{ $members->get($memberId)?->full_name ?? ('Member #'.$memberId) }}</td>
                                        <td class="money">ZMW {{ number_format((float) ($memberBreakdown[$memberId] ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="terms-block">Repayment schedule is not available.</div>
        @endforelse
    </section>

    <section class="section">
        <h3 class="section-title">5. Totals and Financial Summary</h3>
        <table class="totals-table">
            <thead>
                <tr>
                    <th>Financial Metric</th>
                    <th>Amount (ZMW)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Principal</td>
                    <td>ZMW {{ number_format((float) data_get($totals, 'principal_amount', 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Total Processing Fees</td>
                    <td>ZMW {{ number_format((float) data_get($totals, 'processing_fee_amount', 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Total Interest</td>
                    <td>ZMW {{ number_format((float) data_get($totals, 'interest_amount', 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Projected Repayment Total</td>
                    <td>ZMW {{ number_format((float) data_get($totals, 'repayment_amount', 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Total Disbursement</td>
                    <td>ZMW {{ number_format((float) data_get($totals, 'disbursement_amount', 0), 2) }}</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="section">
        <h3 class="section-title">6. Terms and Conditions</h3>
        <div class="terms-block">{{ !empty($wizard['terms_and_conditions']) ? $wizard['terms_and_conditions'] : 'No additional terms and conditions were captured at draft stage.' }}</div>
    </section>

    <section class="signature-section section">
        <h3 class="section-title">7. Review and Sign-Off</h3>
        <p class="signature-note">
            This document is prepared for review by loan officers, management, auditors, and group leadership. Signatures below confirm review of the data, rates, repayment structure, and member allocations contained in this document.
        </p>

        <table class="signature-grid">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-role">Relationship Manager</div>
                    <div class="signature-meta">Name: {{ $relationshipManager?->full_name ?? '________________' }}</div>
                    <div class="signature-meta">Date: _______________________</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-role">Loan Officer</div>
                    <div class="signature-meta">Name: _______________________</div>
                    <div class="signature-meta">Date: _______________________</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-role">Management Approver</div>
                    <div class="signature-meta">Name: _______________________</div>
                    <div class="signature-meta">Date: _______________________</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-role">Group Leader / Representative</div>
                    <div class="signature-meta">Name: _______________________</div>
                    <div class="signature-meta">Date: _______________________</div>
                </td>
            </tr>
        </table>
    </section>

    <footer class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">Prepared by {{ $printedBy?->full_name ?? 'System' }} | Generated {{ $generatedAtValue->format('d M Y, H:i') }} | {{ $branding['organization_name'] ?? config('app.system_name') }}</td>
                <td class="footer-right"><span class="page-counter"></span></td>
            </tr>
        </table>
    </footer>
</div>
</body>
</html>
