@php
    use App\Support\Pdf\FinancialDocumentBranding;

    $branding = $branding ?? FinancialDocumentBranding::resolve($company ?? null);
    $schedulePlan = $loan->getSchedulePlan();
    $principal = (float) $schedulePlan['principal'];
    $interest = (float) $schedulePlan['interest'];
    $processingFee = (float) ($schedulePlan['processing_fee'] ?? 0);
    $financeCharge = round($interest + $processingFee, 2);
    $contractTotal = (float) $schedulePlan['schedule_total'];
    $amountPaid = (float) $loan->amount_paid;
    $outstanding = (float) $loan->outstanding_balance;
    $defaultInterestEntries = collect($defaultInterestEntries ?? []);
    $defaultInterestTotal = round((float) ($defaultInterestTotal ?? 0), 2);
    $totalObligation = round($contractTotal + $defaultInterestTotal, 2);
    $scheduleExpectedSum = collect($repaymentSchedule)->sum('expected_amount');
    $schedulePaidSum = collect($repaymentSchedule)->sum('amount_paid');
    $scheduleRemainingSum = collect($repaymentSchedule)->sum('remaining_amount');
    $paidInstallments = collect($repaymentSchedule)->filter(fn ($row) => in_array($row['status'], ['paid', 'paid_early'], true))->count();
    $remainingInstallments = max(0, count($repaymentSchedule) - $paidInstallments);
    $nextItem = collect($repaymentSchedule)->first(function ($row) {
        return ($row['remaining_amount'] ?? 0) > 0
            && ! in_array($row['status'], ['paid', 'paid_early'], true);
    });
    $repaymentFrequency = match ((string) data_get($loan->metadata, 'repayment_structure', '')) {
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        default => 'Monthly',
    };
    $generatedAt = now()->timezone(config('app.timezone', 'Africa/Lusaka'));
    $includeSignatures = (bool) ($branding['include_signature_blocks'] ?? false);
    $loanDisplayNumber = $loan->loan_number ?: ('LOAN-'.$loan->id);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Repayment Schedule - {{ $loanDisplayNumber }}</title>
    <style>
        @include('pdf.styles.financial-document')

        /*
         | Dompdf does not reliably inset complex tables from @page alone.
         | Keep the required @page rule, and mirror the same inset on body so
         | the printable frame is actually applied for this document.
         */
        @page {
            size: A4 portrait;
            margin: 15mm 14mm 16mm 14mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            margin: 15mm 14mm 16mm 14mm;
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

        .doc-header,
        .doc-title-block,
        .identity-table,
        .summary-strip,
        .summary-block,
        .data-table,
        .note,
        .signature-section,
        .document-certification {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .loan-schedule-document .summary-strip {
            background: #ffffff;
            border: none;
        }

        .loan-schedule-document .summary-strip th,
        .loan-schedule-document .summary-strip td {
            border: none;
            border-right: none;
            background: #ffffff;
        }

        .loan-schedule-document .summary-strip th {
            color: #64748b;
        }

        .loan-schedule-document .summary-strip td {
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="page pdf-document loan-schedule-document">
        @include('pdf.partials.header', [
            'branding' => $branding,
            'documentDescriptor' => 'Loan Repayment Schedule',
            'documentReference' => $loanDisplayNumber,
            'generatedAt' => $generatedAt,
        ])

        <div class="doc-title-block">
            <h1>Loan Repayment Schedule</h1>
            <div class="subtitle">Loan {{ $loanDisplayNumber }}</div>
            <span class="status-badge {{ in_array($loan->status, ['completed', 'settled'], true) ? 'closed' : ($loan->status === 'defaulted' ? 'defaulted' : 'active') }}">
                {{ FinancialDocumentBranding::loanStatusLabel($loan->status) }}
            </span>
        </div>

        <table class="identity-table" role="presentation">
            <tr>
                <td>
                    <div class="section-title">Customer details</div>
                    <table class="field-grid" role="presentation">
                        <tr>
                            <td>
                                <span class="field-label">Customer name</span>
                                <span class="field-value">{{ $loan->customer->full_name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="field-label">Phone</span>
                                <span class="field-value">{{ $loan->customer->phone ?? '—' }}</span>
                            </td>
                        </tr>
                        @if (! empty($loan->customer->email))
                            <tr>
                                <td colspan="2">
                                    <span class="field-label">Email</span>
                                    <span class="field-value">{{ $loan->customer->email }}</span>
                                </td>
                            </tr>
                        @endif
                        @php
                            $customerAddress = collect([
                                $loan->customer->address_line1 ?? null,
                                $loan->customer->city ?? null,
                            ])->filter()->implode(', ');
                        @endphp
                        @if ($customerAddress !== '')
                            <tr>
                                <td colspan="2">
                                    <span class="field-label">Address</span>
                                    <span class="field-value">{{ $customerAddress }}</span>
                                </td>
                            </tr>
                        @endif
                    </table>
                </td>
                <td>
                    <div class="section-title">Loan details</div>
                    <table class="field-grid" role="presentation">
                        <tr>
                            <td>
                                <span class="field-label">Product</span>
                                <span class="field-value">{{ $loan->loanProduct->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="field-label">Tenure</span>
                                <span class="field-value">{{ $loan->tenureDisplayLabel() }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="field-label">Start date</span>
                                <span class="field-value">{{ optional($loan->loan_start_date)->format('d M Y') ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="field-label">Maturity date</span>
                                <span class="field-value">{{ optional($loan->loan_end_date)->format('d M Y') ?? '—' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="field-label">Frequency</span>
                                <span class="field-value">{{ $repaymentFrequency }}</span>
                            </td>
                            <td>
                                <span class="field-label">Instalments</span>
                                <span class="field-value">{{ count($repaymentSchedule) }} ({{ $paidInstallments }} paid / {{ $remainingInstallments }} remaining)</span>
                            </td>
                        </tr>
                        @if ($nextItem)
                            <tr>
                                <td>
                                    <span class="field-label">Next payment date</span>
                                    <span class="field-value">{{ $nextItem['payment_date']->format('d M Y') }}</span>
                                </td>
                                <td>
                                    <span class="field-label">Next payment amount</span>
                                    <span class="field-value">{{ FinancialDocumentBranding::formatMoney($nextItem['remaining_amount']) }}</span>
                                </td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        @php
            $totalObligation = round($contractTotal + $defaultInterestTotal, 2);
        @endphp

        <div class="summary-block">
            <table class="summary-strip" role="presentation">
                <tr>
                    <th>Principal</th>
                    <th>Interest / Finance charge</th>
                    <th>Contract total</th>
                    @if ($defaultInterestTotal > 0.009)
                        <th>Default interest</th>
                        <th>Total obligation</th>
                    @endif
                    <th>Amount paid</th>
                    <th>Outstanding</th>
                </tr>
                <tr>
                    <td>{{ FinancialDocumentBranding::formatMoney($principal) }}</td>
                    <td>{{ FinancialDocumentBranding::formatMoney($financeCharge) }}</td>
                    <td>{{ FinancialDocumentBranding::formatMoney($contractTotal) }}</td>
                    @if ($defaultInterestTotal > 0.009)
                        <td>{{ FinancialDocumentBranding::formatMoney($defaultInterestTotal) }}</td>
                        <td>{{ FinancialDocumentBranding::formatMoney($totalObligation) }}</td>
                    @endif
                    <td>{{ FinancialDocumentBranding::formatMoney($amountPaid) }}</td>
                    <td>{{ FinancialDocumentBranding::formatMoney($outstanding) }}</td>
                </tr>
            </table>
        </div>

        <div class="section-title">Repayment schedule</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 7%;" class="center">No.</th>
                    <th style="width: 16%;" class="center">Due date</th>
                    <th style="width: 18%;" class="num">Expected</th>
                    <th style="width: 18%;" class="num">Paid</th>
                    <th style="width: 18%;" class="num">Remaining</th>
                    <th style="width: 23%;" class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($repaymentSchedule as $scheduleItem)
                    <tr class="repayment-row">
                        <td class="center">{{ $scheduleItem['period'] }}</td>
                        <td class="center">{{ $scheduleItem['payment_date']->format('d M Y') }}</td>
                        <td class="num">{{ FinancialDocumentBranding::formatMoney($scheduleItem['expected_amount']) }}</td>
                        <td class="num">
                            @if (($scheduleItem['amount_paid'] ?? 0) > 0)
                                {{ FinancialDocumentBranding::formatMoney($scheduleItem['amount_paid']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">
                            @if (($scheduleItem['remaining_amount'] ?? 0) > 0)
                                {{ FinancialDocumentBranding::formatMoney($scheduleItem['remaining_amount']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="center">
                            <span class="status-pill {{ FinancialDocumentBranding::scheduleStatusClass($scheduleItem['status'] ?? null) }}">
                                {{ FinancialDocumentBranding::scheduleStatusLabel($scheduleItem['status'] ?? null) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="num">Totals</td>
                    <td class="num">{{ FinancialDocumentBranding::formatMoney($scheduleExpectedSum) }}</td>
                    <td class="num">{{ FinancialDocumentBranding::formatMoney($schedulePaidSum) }}</td>
                    <td class="num">{{ FinancialDocumentBranding::formatMoney($scheduleRemainingSum) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        @if ($defaultInterestEntries->isNotEmpty())
            <div class="section-title">Default interest / penalties</div>
            <p class="note">
                These charges are in addition to the contractual instalment schedule above.
                Outstanding includes active default interest.
            </p>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 14%;" class="center">Posting date</th>
                        <th style="width: 12%;" class="center">Default day</th>
                        <th style="width: 12%;" class="center">Rate</th>
                        <th style="width: 18%;" class="num">Base</th>
                        <th style="width: 18%;" class="num">Amount</th>
                        <th style="width: 26%;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($defaultInterestEntries as $entry)
                        <tr>
                            <td class="center">{{ $entry->posting_date?->format('d M Y') ?? '—' }}</td>
                            <td class="center">Day {{ $entry->default_day }}</td>
                            <td class="center">{{ rtrim(rtrim(number_format((float) $entry->rate, 4, '.', ''), '0'), '.') }}%</td>
                            <td class="num">{{ FinancialDocumentBranding::formatMoney((float) $entry->calculation_base) }}</td>
                            <td class="num">{{ FinancialDocumentBranding::formatMoney((float) $entry->amount) }}</td>
                            <td>{{ data_get($entry->metadata, 'description') ?: ('Default Interest — Day '.$entry->default_day) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="num">Total default interest</td>
                        <td class="num">{{ FinancialDocumentBranding::formatMoney($defaultInterestTotal) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif

        <p class="note">
            Payments must be made on or before the stated due dates. Late or default charges, where applicable,
            are governed by the signed loan agreement and the applicable product terms.
        </p>

        @include('pdf.partials.signatures', [
            'branding' => $branding,
            'customerName' => $loan->customer->full_name ?? '__________________',
            'generatedAt' => $generatedAt,
            'includeSignatures' => $includeSignatures,
        ])

        @include('pdf.partials.footer', [
            'branding' => $branding,
            'generatedAt' => $generatedAt,
            'generatedBy' => auth('admin')->user()?->full_name ?? auth('admin')->user()?->email,
            'disclaimer' => null,
        ])
    </div>
</body>
</html>
