<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Statement — {{ $customer->full_name }} | {{ config('app.system_name') }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9pt; color: #0f172a; margin: 0; }
        h1 { font-size: 16pt; margin: 0 0 4pt; }
        .meta { color: #475569; font-size: 8.5pt; margin-bottom: 12pt; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
        .summary td { border: 1pt solid #cbd5e1; padding: 6pt 8pt; }
        .summary .label { background: #f1f5f9; font-weight: 600; width: 18%; }
        table.ledger { width: 100%; border-collapse: collapse; }
        table.ledger th, table.ledger td { border: 1pt solid #cbd5e1; padding: 5pt 6pt; text-align: left; vertical-align: top; }
        table.ledger th { background: #e2e8f0; font-size: 8pt; text-transform: uppercase; }
        table.ledger .num { text-align: right; white-space: nowrap; }
        tr.info td { background: #f8fafc; color: #64748b; font-style: italic; }
        .opening { background: #eff6ff; padding: 6pt 8pt; margin-bottom: 8pt; border: 1pt solid #93c5fd; }
        @media screen {
            body { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
            .no-print { margin-bottom: 16px; }
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    @php
        $summary = $statement['summary'];
        $rows = $statement['rows'];
        $opening = $statement['opening_balance'];
        $closing = $statement['closing_balance'];
        $filters = $statement['filters'];
    @endphp

    <div class="no-print">
        <button type="button" onclick="window.print()" style="padding:8px 16px;cursor:pointer;">Print</button>
        <a href="{{ route('admin.customers.statement', array_merge([$customer], request()->only(['from_date', 'to_date', 'loan_id']))) }}" style="margin-left:8px;">Back to screen view</a>
    </div>

    <h1>{{ config('app.system_name') }} — Customer Statement</h1>
    <p class="meta">
        <strong>{{ $customer->full_name }}</strong> · {{ $customer->email }} · {{ $customer->phone }}<br>
        Generated {{ now()->format('d M Y, H:i') }}
        @if ($filters['from_date'] || $filters['to_date'])
            · Period: {{ $filters['from_date'] ?? 'start' }} to {{ $filters['to_date'] ?? 'present' }}
        @endif
    </p>

    <table class="summary">
        <tr>
            <td class="label">Loans collected</td><td>{{ $summary['loans_collected'] }}</td>
            <td class="label">Expected settlement</td><td>ZMW {{ number_format($summary['total_expected_settlement'], 2) }}</td>
            <td class="label">Net paid</td><td>ZMW {{ number_format($summary['total_net_paid'], 2) }}</td>
        </tr>
        <tr>
            <td class="label">Refunded</td><td>ZMW {{ number_format($summary['total_refunded'], 2) }}</td>
            <td class="label">Outstanding</td><td>ZMW {{ number_format($summary['total_outstanding'], 2) }}</td>
            <td class="label">Customer credit</td><td>ZMW {{ number_format($summary['total_suspense'], 2) }}</td>
        </tr>
    </table>

    @if ($filters['from_date'] && ($opening['balance_owed'] > 0 || $opening['customer_credit'] > 0))
        <div class="opening">
            Opening balance (before {{ $filters['from_date'] }}):
            @if ($opening['customer_credit'] > 0)
                Customer credit ZMW {{ number_format($opening['customer_credit'], 2) }}
            @else
                Balance owed ZMW {{ number_format($opening['balance_owed'], 2) }}
            @endif
        </div>
    @endif

    <table class="ledger">
        <thead>
            <tr>
                <th>Date</th>
                <th>Loan ref</th>
                <th>Description</th>
                <th>Type</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Running balance</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $isInfo = in_array($row['transaction_type'], ['schedule', 'settlement', 'suspense'], true);
                    $rb = $row['running_balance'] ?? ['balance_owed' => 0, 'customer_credit' => 0];
                @endphp
                <tr class="{{ $isInfo && ! $row['is_cash'] ? 'info' : '' }}">
                    <td>{{ $row['date']->format('d/m/Y') }}</td>
                    <td>{{ $row['loan_reference'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ ucfirst($row['transaction_type']) }}</td>
                    <td class="num">{{ $row['debit'] ? number_format($row['debit'], 2) : '—' }}</td>
                    <td class="num">{{ $row['credit'] ? number_format($row['credit'], 2) : '—' }}</td>
                    <td class="num">
                        @if ($isInfo && ! $row['is_cash'])
                            —
                        @elseif ($rb['customer_credit'] > 0)
                            Credit {{ number_format($rb['customer_credit'], 2) }}
                        @else
                            Owed {{ number_format($rb['balance_owed'], 2) }}
                        @endif
                    </td>
                    <td>{{ $row['reference'] ?? ($row['notes'] ?? '') }}</td>
                </tr>
            @endforeach
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="6" class="num" style="font-weight:bold;">Closing balance</td>
                    <td class="num" style="font-weight:bold;">
                        @if ($closing['customer_credit'] > 0)
                            Credit {{ number_format($closing['customer_credit'], 2) }}
                        @else
                            Owed {{ number_format($closing['balance_owed'], 2) }}
                        @endif
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
