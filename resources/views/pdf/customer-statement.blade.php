@php
    use App\Support\Pdf\DocumentPreparedBy;
    use App\Support\Pdf\FinancialDocumentBranding;
    use Carbon\Carbon;

    $branding = $branding ?? FinancialDocumentBranding::resolve($company ?? null);
    $summary = $statement['summary'];
    $rows = $statement['rows'];
    $opening = $statement['opening_balance'];
    $closing = $statement['closing_balance'];
    $filters = $statement['filters'];
    $loans = $statement['loans'];
    $generatedAt = now()->timezone(config('app.timezone', 'Africa/Lusaka'));
    $includeSignatures = (bool) ($branding['include_signature_blocks'] ?? false);
    $timezone = config('app.timezone', 'Africa/Lusaka');
    $preparedBy = DocumentPreparedBy::fromAuth();

    $customerNumber = $customer->customer_number
        ?? $customer->account_number
        ?? ('CUST-'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT));

    $selectedLoanId = $filters['loan_id'] ?? null;
    $selectedLoan = $selectedLoanId
        ? $loans->firstWhere('id', (int) $selectedLoanId)
        : null;
    $isSingleLoanScope = $selectedLoan !== null;
    $selectedLoanLabel = $isSingleLoanScope
        ? ($selectedLoan->loan_number ?: ('LOAN-'.$selectedLoan->id))
        : null;
    $scopeLabel = $isSingleLoanScope ? 'Single Loan' : 'All Loans';
    $scopeSummaryLabel = $isSingleLoanScope
        ? 'Summary for loan '.$selectedLoanLabel
        : 'Summary for all loans';
    $outstandingLabel = $isSingleLoanScope
        ? 'Loan Outstanding — '.$selectedLoanLabel
        : 'Account Outstanding — All Loans';

    $formatFilterDate = static function (?string $date): ?string {
        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->timezone(config('app.timezone', 'Africa/Lusaka'))->format('d M Y');
    };

    $fromLabel = $formatFilterDate($filters['from_date'] ?? null);
    $toLabel = $formatFilterDate($filters['to_date'] ?? null);
    $periodLabel = 'All Time';
    if ($fromLabel && $toLabel) {
        $periodLabel = $fromLabel.' to '.$toLabel;
    } elseif ($fromLabel) {
        $periodLabel = 'From '.$fromLabel;
    } elseif ($toLabel) {
        $periodLabel = 'Until '.$toLabel;
    }

    $typeLabel = static function (string $type): string {
        return match ($type) {
            'disbursement' => 'Booking',
            'payment' => 'Repayment',
            'refund' => 'Refund',
            'default_interest' => 'Default interest',
            'settlement' => 'Settlement',
            default => str_replace('_', ' ', $type),
        };
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Statement - {{ $customer->full_name }}</title>
    <style>
        @include('pdf.styles.financial-document')

        /*
         | Statement-specific page frame.
         | Dompdf does not always honour @page alone for complex tables, so body
         | mirrors the same inset used by the loan schedule PDF lesson.
         */
        @page {
            size: A4 portrait;
            margin: 15mm 14mm 17mm 14mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            margin: 15mm 14mm 17mm 14mm;
        }

        .pdf-document {
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .pdf-document table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            box-sizing: border-box;
        }

        .doc-header .brand-descriptor {
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.4px;
            margin-top: 2px;
        }

        .doc-title-block {
            margin: 4px 0 6px;
            text-align: left;
        }

        .doc-title-block .subtitle {
            font-size: 9pt;
            font-weight: 700;
            color: #0f172a;
        }

        .statement-title-meta {
            margin-top: 2px;
            font-size: 7.5pt;
            color: #334155;
            line-height: 1.35;
        }

        .statement-title-meta .meta-row {
            margin: 0;
        }

        .statement-title-meta .meta-strong {
            font-weight: 700;
            color: #0f172a;
        }

        .identity-table {
            margin-bottom: 5px;
        }

        .summary-heading {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 0 0 3px;
        }

        .summary-strip {
            margin: 2px 0 6px;
            background: #ffffff;
            border: none;
        }

        .summary-strip th,
        .summary-strip td {
            padding: 3px 4px;
            border: none;
            border-right: none;
            background: #ffffff;
            text-align: left;
        }

        .summary-strip th:last-child,
        .summary-strip td:last-child {
            border-right: none;
        }

        .note {
            margin: 2px 0 4px;
            font-size: 6.5pt;
        }

        .section-title {
            margin-bottom: 2px;
        }

        .txn-table th,
        .txn-table td {
            font-size: 6.5pt;
            padding: 2.5px 2.5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .txn-table th {
            font-size: 5.8pt;
            padding: 3px 2px;
            letter-spacing: 0.15px;
            line-height: 1.15;
        }

        .type-cell {
            text-align: center;
            text-transform: uppercase;
            font-size: 5.8pt;
            font-weight: 700;
            letter-spacing: 0.15px;
            color: #334155;
        }

        .closing-strip th {
            font-size: 6pt;
        }

        .closing-strip td {
            font-size: 7.5pt;
        }

        .signature-section {
            margin-top: 8px;
        }

        .signature-line {
            margin-top: 18px;
        }

        .statement-footer-note {
            margin-top: 4px;
            font-size: 6pt;
            color: #64748b;
            line-height: 1.3;
        }

        .page-footer-fixed {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -11mm;
            font-size: 6pt;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 2px;
        }
    </style>
</head>
<body>
    <div class="pdf-document">
        @include('pdf.partials.header', [
            'branding' => $branding,
            'documentDescriptor' => 'Customer Statement',
            'documentReference' => $customerNumber,
            'generatedAt' => $generatedAt,
        ])

        <div class="doc-title-block">
            <div class="subtitle">{{ $customer->full_name }} · {{ $customerNumber }}</div>
            <div class="statement-title-meta">
                <p class="meta-row"><span class="meta-strong">Scope:</span> {{ $scopeLabel }}@if ($isSingleLoanScope) · <span class="meta-strong">Loan:</span> {{ $selectedLoanLabel }}@endif</p>
                @if ($isSingleLoanScope && $selectedLoan->loanProduct?->name)
                    <p class="meta-row"><span class="meta-strong">Product:</span> {{ $selectedLoan->loanProduct->name }}</p>
                @endif
                <p class="meta-row"><span class="meta-strong">Statement period:</span> {{ $periodLabel }} · <span class="meta-strong">Generated:</span> {{ $generatedAt->format('d M Y, H:i') }}</p>
            </div>
        </div>

        <table class="identity-table" role="presentation">
            <tr>
                <td>
                    <div class="section-title">Customer details</div>
                    <table class="field-grid" role="presentation">
                        <tr>
                            <td>
                                <span class="field-label">Customer name</span>
                                <span class="field-value">{{ $customer->full_name }}</span>
                            </td>
                            <td>
                                <span class="field-label">Customer number</span>
                                <span class="field-value">{{ $customerNumber }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="field-label">Telephone</span>
                                <span class="field-value">{{ $customer->phone ?: '—' }}</span>
                            </td>
                            <td>
                                <span class="field-label">Email</span>
                                <span class="field-value">{{ $customer->email ?: '—' }}</span>
                            </td>
                        </tr>
                    </table>
                </td>
                <td>
                    <div class="section-title">Statement details</div>
                    <table class="field-grid" role="presentation">
                        <tr>
                            <td>
                                <span class="field-label">Scope</span>
                                <span class="field-value">{{ $scopeLabel }}</span>
                            </td>
                            <td>
                                <span class="field-label">Statement period</span>p
                                <span class="field-value">{{ $periodLabel }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="field-label">{{ $isSingleLoanScope ? 'Selected loan' : 'Loans included' }}</span>
                                <span class="field-value">
                                    @if ($isSingleLoanScope)
                                        {{ $selectedLoanLabel }}
                                    @else
                                        {{ $loans->count() }}
                                    @endif
                                </span>
                            </td>
                            <td>
                                <span class="field-label">Generated by</span>
                                <span class="field-value">{{ $preparedBy['name'] ?: '—' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="field-label">Generated date</span>
                                <span class="field-value">{{ $generatedAt->format('d M Y, H:i') }}</span>
                            </td>
                            <td>
                                @if ($isSingleLoanScope && $selectedLoan->loanProduct?->name)
                                    <span class="field-label">Product</span>
                                    <span class="field-value">{{ $selectedLoan->loanProduct->name }}</span>
                                @elseif ($preparedBy['role'])
                                    <span class="field-label">Prepared role</span>
                                    <span class="field-value">{{ $preparedBy['role'] }}</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="summary-heading">{{ $scopeSummaryLabel }}</div>
        <table class="summary-strip" role="presentation">
            <tr>
                <th>Loans</th>
                <th>{{ $isSingleLoanScope ? 'Loan amount' : 'Total amount' }}</th>
                <th>Paid</th>
                <th>{{ ($summary['total_suspense'] ?? 0) > 0 ? 'Customer credit' : 'Outstanding' }}</th>
            </tr>
            <tr>
                <td>{{ $summary['loans_collected'] }}</td>
                <td>{{ FinancialDocumentBranding::formatMoney($summary['total_expected_settlement']) }}</td>
                <td>{{ FinancialDocumentBranding::formatMoney($summary['total_net_paid']) }}</td>
                <td>{{ FinancialDocumentBranding::formatMoney(($summary['total_suspense'] ?? 0) > 0 ? $summary['total_suspense'] : $summary['total_outstanding']) }}</td>
            </tr>
        </table>

        @if (($filters['from_date'] ?? null) && (($opening['balance_owed'] ?? 0) > 0 || ($opening['customer_credit'] ?? 0) > 0))
            <table class="summary-strip" role="presentation">
                <tr>
                    <th>Opening balance owed</th>
                    <th>Opening customer credit</th>
                </tr>
                <tr>
                    <td>{{ FinancialDocumentBranding::formatMoney($opening['balance_owed'] ?? 0) }}</td>
                    <td>{{ FinancialDocumentBranding::formatMoney($opening['customer_credit'] ?? 0) }}</td>
                </tr>
            </table>
        @endif

        <div class="section-title">Transaction history</div>
        <p class="note">
            Posted activity only · {{ $timezone }} · Debits increase owed · Credits reduce owed ·
            Running balance is {{ $isSingleLoanScope ? 'for the selected loan' : 'customer-level across included loans' }}.
        </p>

        @if ($rows->isEmpty())
            <div class="empty-state">
                No posted transactions were found for the selected statement period.
            </div>
        @else
            <table class="data-table txn-table">
                <thead>
                    <tr>
                        <th style="width: 11%;" class="center">Date &amp; time</th>
                        <th style="width: 16%;">Loan</th>
                        <th style="width: 32%;">Description</th>
                        <th style="width: 9%;" class="center">Type</th>
                        <th style="width: 11%;" class="num">Debit</th>
                        <th style="width: 11%;" class="num">Credit</th>
                        <th style="width: 10%;" class="num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $occurredAt = ($row['date'] ?? now())->copy()->timezone($timezone);
                            $secondary = $row['secondary_description'] ?? $row['notes'] ?? null;
                            $reference = $row['reference'] ?? null;
                            $rb = $row['running_balance'] ?? ['balance_owed' => 0, 'customer_credit' => 0];
                            $isInfo = ! ($row['is_cash'] ?? true);
                        @endphp
                        <tr>
                            <td class="center">
                                <span class="datetime-primary">{{ $occurredAt->format('d M Y') }}</span>
                                <span class="datetime-secondary">{{ $occurredAt->format('H:i') }}</span>
                            </td>
                            <td>{{ $row['loan_reference'] ?? ($row['loan_id'] ?? '—') }}</td>
                            <td>
                                <span class="desc-primary">{{ $row['description'] }}</span>
                                @if ($secondary)
                                    <span class="desc-secondary">{{ $secondary }}</span>
                                @endif
                                @if ($reference)
                                    <span class="desc-secondary">Ref: {{ $reference }}</span>
                                @endif
                            </td>
                            <td class="type-cell">{{ $typeLabel((string) $row['transaction_type']) }}</td>
                            <td class="num">
                                @if (($row['debit'] ?? null) !== null)
                                    {{ FinancialDocumentBranding::formatMoney($row['debit']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">
                                @if (($row['credit'] ?? null) !== null)
                                    {{ FinancialDocumentBranding::formatMoney($row['credit']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">
                                @if ($isInfo)
                                    —
                                @elseif (($rb['customer_credit'] ?? 0) > 0)
                                    {{ FinancialDocumentBranding::formatMoney($rb['customer_credit']) }} cr
                                @else
                                    {{ FinancialDocumentBranding::formatMoney($rb['balance_owed'] ?? 0) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="summary-block">
            <div class="summary-heading">Closing balance</div>
            <table class="summary-strip closing-strip" role="presentation">
                <tr>
                    <th>Closing balance owed</th>
                    <th>Customer credit</th>
                    <th>{{ $outstandingLabel }}</th>
                </tr>
                <tr>
                    <td>{{ FinancialDocumentBranding::formatMoney($closing['balance_owed'] ?? 0) }}</td>
                    <td>{{ FinancialDocumentBranding::formatMoney($closing['customer_credit'] ?? 0) }}</td>
                    <td>{{ FinancialDocumentBranding::formatMoney($summary['total_outstanding']) }}</td>
                </tr>
            </table>
        </div>

        @include('pdf.partials.signatures', [
            'branding' => $branding,
            'customerName' => $customer->full_name,
            'generatedAt' => $generatedAt,
            'includeSignatures' => $includeSignatures,
            'preparedBy' => $preparedBy,
        ])

        <div class="statement-footer-note">
            This statement reflects posted transactions recorded in the {{ $branding['organization_name'] }} system up to the generated date.
            Please contact support if you identify any discrepancy.
            @if (! empty($branding['support_email']) || ! empty($branding['support_phone']))
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
            @endif
        </div>

        <div class="page-footer-fixed">
            Customer Statement · {{ $customerNumber }} · Scope: {{ $scopeLabel }}@if ($isSingleLoanScope) · Loan ID {{ $selectedLoan->id }}@endif
            · Generated {{ $generatedAt->format('d M Y, H:i') }}
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $size = 6.5;
            $color = [0.39, 0.45, 0.55];
            $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = 595.28 - 40 - $width;
            $y = 828;
            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>
</body>
</html>
