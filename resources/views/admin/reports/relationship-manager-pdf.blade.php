<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Relationship Manager Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        h3 { font-size: 12px; margin: 14px 0 6px; }
        p.meta { color: #475569; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        th { background: #e2e8f0; text-align: left; font-weight: bold; }
        .text-right { text-align: right; }
        .muted { color: #64748b; }
        .section { margin-bottom: 16px; }
    </style>
</head>
<body>
    @php
        $dataset = $filters['export_dataset'] ?? 'summary';
        $showSummary = $dataset === 'summary';
        $showCustomers = $dataset === 'customers';
        $showLoans = $dataset === 'loans';
        $showRepayments = $dataset === 'repayments';
    @endphp

    <h1>Relationship Manager Report</h1>
    <p class="meta">
        Generated: {{ $generatedAt->format('d M Y H:i') }}<br>
        Branch: {{ $filters['branch_id'] ? ($branchOptions->firstWhere('id', (int) $filters['branch_id'])->name ?? 'Selected Branch') : 'All Branches' }} |
        Mode: {{ ucfirst($filters['mode']) }} |
        PAR Bucket: {{ strtoupper($filters['par_bucket']) }} |
        Customer Type: {{ ucfirst($filters['customer_type']) }} |
        Export Data: {{ ucfirst($dataset) }} |
        Date Range:
        {{ $filters['date_from'] ? \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') : 'Any' }}
        -
        {{ $filters['date_to'] ? \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') : 'Any' }}
    </p>

    @if($showSummary)
        <div class="section">
            <h2>Summary Totals</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Total Portfolio Value</th>
                        <td class="text-right">ZMW {{ number_format($summary['total_portfolio_value'] ?? 0, 2) }}</td>
                        <th>Total Outstanding</th>
                        <td class="text-right">ZMW {{ number_format($summary['total_outstanding_balance'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Total PAR Amount</th>
                        <td class="text-right">ZMW {{ number_format($summary['total_par_amount'] ?? 0, 2) }}</td>
                        <th>PAR Ratio</th>
                        <td class="text-right">{{ number_format($summary['par_ratio'] ?? 0, 2) }}%</td>
                    </tr>
                    <tr>
                        <th>Total Disbursed (Range)</th>
                        <td class="text-right">ZMW {{ number_format($summary['total_disbursed_amount'] ?? 0, 2) }}</td>
                        <th>Total Collections (Range)</th>
                        <td class="text-right">ZMW {{ number_format($summary['total_collections_amount'] ?? 0, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Relationship Manager Overview</h2>
            <table>
                <thead>
                    <tr>
                        <th>Relationship Manager</th>
                        <th class="text-right">Portfolio Value</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">PAR Amount</th>
                        <th class="text-right">PAR Ratio</th>
                        <th class="text-right">Individuals</th>
                        <th class="text-right">Groups</th>
                        <th class="text-right">Disbursed</th>
                        <th class="text-right">Collections</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportRows as $row)
                        <tr>
                            <td>{{ $row['manager']->full_name }}</td>
                            <td class="text-right">ZMW {{ number_format($row['total_portfolio_value'], 2) }}</td>
                            <td class="text-right">ZMW {{ number_format($row['total_outstanding_balance'], 2) }}</td>
                            <td class="text-right">ZMW {{ number_format($row['par_amount'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['par_ratio'], 2) }}% ({{ $row['par_status'] }})</td>
                            <td class="text-right">{{ number_format($row['individual_customers_count']) }}</td>
                            <td class="text-right">{{ number_format($row['groups_count']) }}</td>
                            <td class="text-right">ZMW {{ number_format($row['loans_disbursed_amount'], 2) }}</td>
                            <td class="text-right">ZMW {{ number_format($row['collections_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">No report rows found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($showCustomers || $showLoans || $showRepayments)
        @foreach($reportRows as $row)
            <div class="section">
                <h2>{{ $row['manager']->full_name }} - Detail</h2>

                @if($showCustomers)
                    <h3>Customers Linked</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Group</th>
                                <th>Company</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($row['details']['customers'] as $customer)
                                <tr>
                                    <td>{{ $customer['name'] }}</td>
                                    <td>{{ ucfirst($customer['portfolio_type']) }}</td>
                                    <td>{{ $customer['group_name'] ?? '—' }}</td>
                                    <td>{{ $customer['company_name'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">No customers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif

                @if($showLoans)
                    <h3>Loans Linked</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Loan #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th class="text-right">Outstanding</th>
                                <th class="text-right">Overdue</th>
                                <th>PAR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($row['details']['loans'] as $loan)
                                <tr>
                                    <td>{{ $loan['loan_number'] }}</td>
                                    <td>{{ $loan['customer_name'] }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $loan['status'])) }}</td>
                                    <td class="text-right">ZMW {{ number_format($loan['outstanding_balance'], 2) }}</td>
                                    <td class="text-right">ZMW {{ number_format($loan['overdue_amount'], 2) }}</td>
                                    <td>{{ $loan['par_status'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No loans.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif

                @if($showRepayments)
                    <h3>Repayment History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Repayment #</th>
                                <th>Loan #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th class="text-right">Amount</th>
                                <th>Channel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($row['details']['repayments'] as $repayment)
                                <tr>
                                    <td>{{ $repayment['repayment_number'] }}</td>
                                    <td>{{ $repayment['loan_number'] }}</td>
                                    <td>{{ $repayment['customer_name'] }}</td>
                                    <td>{{ $repayment['processed_at']?->format('d M Y H:i') ?? '—' }}</td>
                                    <td class="text-right">ZMW {{ number_format($repayment['amount'], 2) }}</td>
                                    <td>{{ $repayment['channel_name'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No repayments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
