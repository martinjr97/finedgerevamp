<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Repayment Schedule - {{ $loan->loan_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1e293b;
            padding: 30px;
            background: #ffffff;
        }
        
        .header {
            margin-bottom: 28px;
            padding: 14px 18px;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #0f172a;
            border-radius: 6px;
            background: #f8fafc;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-logo-cell {
            width: 88px;
            padding-right: 14px;
        }

        .header-logo-wrap {
            width: 72px;
            height: 72px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            text-align: center;
            line-height: 68px;
        }

        .header-logo {
            max-width: 64px;
            max-height: 64px;
            vertical-align: middle;
        }

        .header-logo-fallback {
            font-size: 24pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 72px;
            display: inline-block;
        }

        .header-branding {
            padding-right: 14px;
        }
        
        .system-name {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.8px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        
        .system-tagline {
            font-size: 9.5pt;
            color: #475569;
            font-style: italic;
            margin-bottom: 8px;
        }

        .system-address {
            font-size: 9pt;
            color: #334155;
            line-height: 1.4;
        }

        .header-contact-cell {
            width: 215px;
            text-align: right;
            border-left: 1px solid #e2e8f0;
            padding-left: 14px;
        }

        .contact-title {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .contact-line {
            font-size: 9pt;
            color: #334155;
            margin-bottom: 3px;
            word-break: break-word;
        }

        .contact-line:last-child {
            margin-bottom: 0;
        }
        
        .document-title {
            text-align: center;
            margin: 30px 0 25px 0;
        }
        
        .document-title h1 {
            font-size: 22pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        
        .document-title .loan-number {
            font-size: 12pt;
            color: #64748b;
            font-weight: normal;
        }
        
        .loan-summary {
            margin: 30px 0;
            padding: 22px 24px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #0f172a;
            border-radius: 6px;
        }

        .summary-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .summary-row {
            display: table-row;
        }

        .summary-item {
            display: table-cell;
            width: 33.333%;
            padding: 12px 14px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
            background-color: transparent;
        }

        .summary-item + .summary-item {
            border-left: 1px solid #e2e8f0;
        }

        .summary-row:last-child .summary-item {
            border-bottom: none;
        }

        .summary-label {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .summary-value {
            font-size: 9.5pt;
            color: #0f172a;
            font-weight: 600;
            line-height: 1.35;
            word-break: break-word;
        }

        .summary-value.amount {
            text-align: right;
            font-size: 10pt;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum" 1;
        }

        .summary-value.amount .currency {
            font-size: 8.5pt;
            letter-spacing: 0.8px;
            color: #64748b;
            font-weight: 700;
            margin-right: 4px;
            vertical-align: baseline;
        }

        .summary-value.amount .money {
            color: #0f172a;
        }

        .summary-value.amount.strong .money {
            font-weight: 600;
            color: #0b3b72;
        }
        
        .schedule-section {
            margin-top: 35px;
        }
        
        .schedule-title {
            font-size: 14pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0f172a;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .schedule-table thead {
            background-color: #0f172a;
            color: #ffffff;
        }
        
        .schedule-table th {
            padding: 12px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            border: 1px solid #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .schedule-table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #cbd5e1;
            font-size: 10pt;
        }
        
        .schedule-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .schedule-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .schedule-table .amount {
            font-weight: 600;
            color: #0f172a;
        }
        
        .status {
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .status-paid_early {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        
        .status-partial {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        
        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .status-upcoming {
            background-color: #e5e7eb;
            color: #374151;
            border: 1px solid #9ca3af;
        }
        
        .schedule-table tfoot {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
        }
        
        .schedule-table tfoot td {
            padding: 12px 10px;
            font-size: 11pt;
            border: 1px solid #334155;
        }
        
        .schedule-table tfoot .amount {
            color: #ffffff;
            font-weight: bold;
        }
        
        .signature-section {
            margin-top: 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            padding-top: 30px;
            border-top: 2px solid #cbd5e1;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 2px solid #0f172a;
            margin: 70px 0 15px 0;
            width: 100%;
        }
        
        .signature-label {
            font-weight: bold;
            font-size: 11pt;
            color: #0f172a;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .signature-name {
            font-size: 10pt;
            color: #475569;
            margin-top: 8px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 9pt;
            color: #64748b;
        }
        
        .footer p {
            margin-bottom: 5px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @media print {
            body {
                padding: 20px;
            }
            
            .schedule-table {
                page-break-inside: auto;
            }
            
            .schedule-table thead {
                display: table-header-group;
            }
            
            .schedule-table tfoot {
                display: table-footer-group;
            }
            
            .schedule-table tbody tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    @php
        $logoDataUri = null;
        foreach (['img/logo.png', 'img/logo.png'] as $logoCandidate) {
            $logoPath = public_path($logoCandidate);
            if (is_file($logoPath)) {
                $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    default => 'image/png',
                };

                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
                break;
            }
        }

        $supportAddress = collect([
            config('app.support_address_line1'),
            config('app.support_city'),
            config('app.support_country'),
        ])->filter()->implode(', ');
        $supportPhone = config('app.support_phone');
        $supportEmail = config('app.support_email');
        $websiteUrl = config('app.website_url');
        $displayWebsite = $websiteUrl ? preg_replace('#^https?://#', '', rtrim($websiteUrl, '/')) : null;
    @endphp

    {{-- Header with System Name --}}
    <div class="header">
        <table class="header-table" role="presentation">
            <tr>
                <td class="header-logo-cell">
                    <div class="header-logo-wrap">
                        @if($logoDataUri)
                            <img src="{{ $logoDataUri }}" alt="Company Logo" class="header-logo">
                        @else
                            <span class="header-logo-fallback">
                                {{ strtoupper(substr(config('app.system_name', 'LMS'), 0, 1)) }}
                            </span>
                        @endif
                    </div>
                </td>
                <td class="header-branding">
                    <div class="system-name">{{ config('app.system_name', 'Loan Management System') }}</div>
                    <div class="system-tagline">Official Loan Repayment Schedule Document</div>
                    <div class="system-address">{{ $supportAddress ?: 'Customer Support Office' }}</div>
                </td>
                <td class="header-contact-cell">
                    <div class="contact-title">Contact Details</div>
                    <div class="contact-line"><strong>Tel:</strong> {{ $supportPhone ?: 'N/A' }}</div>
                    <div class="contact-line"><strong>Email:</strong> {{ $supportEmail ?: 'N/A' }}</div>
                    @if($displayWebsite)
                        <div class="contact-line"><strong>Web:</strong> {{ $displayWebsite }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Document Title --}}
    <div class="document-title">
        <h2>Loan Repayment Schedule</h2>
        <p class="loan-number">Loan Number: <strong>{{ $loan->loan_number }}</strong></p>
    </div>

    {{-- Loan Summary --}}
    <div class="loan-summary">
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-label">Loan Number</div>
                    <div class="summary-value">{{ $loan->loan_number }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Customer Name</div>
                    <div class="summary-value">{{ $loan->customer->full_name ?? 'N/A' }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Customer Phone</div>
                    <div class="summary-value">{{ $loan->customer->phone ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-label">Product</div>
                    <div class="summary-value">{{ $loan->loanProduct->name ?? 'N/A' }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Tenure</div>
                    <div class="summary-value">{{ $loan->tenure_months }} {{ $loan->tenure_months === 1 ? 'Month' : 'Months' }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Company</div>
                    <div class="summary-value">{{ $company->name ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-label">Principal Amount</div>
                    <div class="summary-value amount strong">
                        <span class="currency">ZMW</span><span class="money">{{ number_format($loan->principal_amount, 2) }}</span>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">{{ $loan->showsDailyAccrualDisclosure() ? 'Booked Loan Total' : 'Booked / Contract Total' }}</div>
                    <div class="summary-value amount strong">
                        <span class="currency">ZMW</span><span class="money">{{ number_format($loan->total_amount, 2) }}</span>
                    </div>
                </div>
                @if($loan->showsDailyAccrualDisclosure())
                <div class="summary-item">
                    <div class="summary-label">Projected Repayment Total</div>
                    <div class="summary-value amount strong">
                        <span class="currency">ZMW</span><span class="money">{{ number_format($loan->getProjectedTotalAmount(), 2) }}</span>
                    </div>
                </div>
                @endif
                <div class="summary-item">
                    <div class="summary-label">Booked Outstanding Balance</div>
                    <div class="summary-value amount strong">
                        <span class="currency">ZMW</span><span class="money">{{ number_format($loan->outstanding_balance, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-label">Amount Paid</div>
                    <div class="summary-value amount">
                        <span class="currency">ZMW</span><span class="money">{{ number_format($loan->amount_paid, 2) }}</span>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Start Date</div>
                    <div class="summary-value">{{ $loan->loan_start_date->format('d M Y') }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">End Date</div>
                    <div class="summary-value">{{ $loan->loan_end_date->format('d M Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Repayment Schedule Table --}}
    <div class="schedule-section">
        <div class="schedule-title">Repayment Schedule Details</div>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Payment Date</th>
                    <th>Expected Amount (ZMW)</th>
                    <th>Amount Paid (ZMW)</th>
                    <th>Remaining (ZMW)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($repaymentSchedule as $scheduleItem)
                    @php
                        $statusClasses = [
                            'paid' => 'status-paid',
                            'paid_early' => 'status-paid_early',
                            'partial' => 'status-partial',
                            'overdue' => 'status-overdue',
                            'upcoming' => 'status-upcoming',
                        ];
                        $statusClass = $statusClasses[$scheduleItem['status']] ?? 'status-upcoming';
                        $statusLabels = [
                            'paid' => 'Paid',
                            'paid_early' => 'Paid Early',
                            'partial' => 'Partial',
                            'overdue' => 'Overdue',
                            'upcoming' => 'Upcoming',
                        ];
                        $statusLabel = $statusLabels[$scheduleItem['status']] ?? ucfirst($scheduleItem['status']);
                    @endphp
                    <tr>
                        <td><strong>{{ $scheduleItem['period'] }}</strong>/{{ $loan->tenure_months }}</td>
                        <td>{{ $scheduleItem['payment_date']->format('d M Y') }}</td>
                        <td class="amount">ZMW {{ number_format($scheduleItem['expected_amount'], 2) }}</td>
                        <td class="amount">
                            @if($scheduleItem['amount_paid'] > 0)
                                ZMW {{ number_format($scheduleItem['amount_paid'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="amount">
                            @if($scheduleItem['remaining_amount'] > 0)
                                ZMW {{ number_format($scheduleItem['remaining_amount'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="status {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right; padding-right: 15px;"><strong>TOTAL:</strong></td>
                    <td class="amount">ZMW {{ number_format($loan->total_amount, 2) }}</td>
                    <td class="amount">ZMW {{ number_format($loan->amount_paid, 2) }}</td>
                    <td class="amount">ZMW {{ number_format($loan->outstanding_balance, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Signature Section --}}
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-label">Customer Signature</div>
            <div class="signature-name">{{ $loan->customer->full_name ?? 'N/A' }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-label">Authorized Signatory</div>
            <div class="signature-name">{{ config('app.system_name', 'Loan Management System') }}</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p><strong>{{ config('app.system_name', 'Loan Management System') }}</strong></p>
        @if($company && $company->contact_email)
            <p>For inquiries, please contact: {{ $company->contact_email }}</p>
        @endif
        <p>Document generated on {{ now()->format('d M Y, h:i A') }}</p>
        <p style="margin-top: 10px; font-size: 8pt; font-style: italic;">This is an official computer-generated document and is valid without physical signature.</p>
    </div>
</body>
</html>
