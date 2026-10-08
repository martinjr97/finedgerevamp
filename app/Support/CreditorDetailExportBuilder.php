<?php

namespace App\Support;

use App\Models\Creditor;
use Illuminate\Support\Str;

class CreditorDetailExportBuilder
{
    /**
     * @return array<int, array{title: string, rows: array<int, array<int, string|null>>}>
     */
    public function sheets(Creditor $creditor): array
    {
        $creditor->loadMissing([
            'expenseTransactions.sourceBank',
            'expenseTransactions.sourceWallet',
            'expenseTransactions.creator',
            'expenseTransactions.expenseCategory',
            'conversions.destinationBank',
            'conversions.destinationWallet',
            'conversions.createdBy',
        ]);

        $payments = $creditor->expenseTransactions;
        $totalPaid = (float) $payments->sum('amount');
        $outstanding = (float) $creditor->amount;
        $impliedOriginal = round($outstanding + $totalPaid, 2);
        $conversions = $creditor->conversions;
        $totalConverted = (float) $conversions->sum('amount');

        $summaryRows = [
            ['Field', 'Value'],
            ['Creditor name', $creditor->name],
            ['Status', $creditor->is_active ? 'Active' : 'Inactive'],
            ['Due date', $creditor->due_date?->format('Y-m-d') ?? ''],
            ['Outstanding balance (ZMW)', number_format($outstanding, 2, '.', '')],
            ['Total paid via expense payments (ZMW)', number_format($totalPaid, 2, '.', '')],
            ['Payment count', (string) $payments->count()],
            ['Implied original liability (ZMW)', number_format($impliedOriginal, 2, '.', '')],
            ['Total converted to bank/wallet (ZMW)', number_format($totalConverted, 2, '.', '')],
            ['Conversion count', (string) $conversions->count()],
            ['Description', $creditor->description ?? ''],
            ['Notes', $creditor->notes ?? ''],
            ['Exported at', now()->format('Y-m-d H:i:s')],
        ];

        $paymentRows = [
            ['Date', 'Transaction #', 'Amount (ZMW)', 'Source', 'Reference', 'Description', 'Category', 'Recorded by', 'Created at'],
        ];
        foreach ($payments as $payment) {
            $source = $payment->sourceBank?->name ?? $payment->sourceWallet?->name ?? '';
            $recordedBy = $payment->creator
                ? trim(($payment->creator->first_name ?? '').' '.($payment->creator->last_name ?? '')) ?: ($payment->creator->email ?? '')
                : '';

            $paymentRows[] = [
                $payment->transaction_date?->format('Y-m-d') ?? '',
                $payment->transaction_number,
                number_format((float) $payment->amount, 2, '.', ''),
                $source,
                $payment->reference_number ?? '',
                $payment->description,
                FinancialCategoryCatalog::transactionCategoryLabel($payment),
                $recordedBy,
                $payment->created_at?->format('Y-m-d H:i:s') ?? '',
            ];
        }

        $conversionRows = [
            ['Date', 'Amount (ZMW)', 'Destination type', 'Destination', 'Notes', 'Recorded by'],
        ];
        foreach ($conversions as $conversion) {
            $destination = match (strtoupper((string) $conversion->destination_type)) {
                'BANK' => $conversion->destinationBank?->name,
                'WALLET' => $conversion->destinationWallet?->name,
                default => null,
            };
            $recordedBy = $conversion->createdBy
                ? trim(($conversion->createdBy->first_name ?? '').' '.($conversion->createdBy->last_name ?? ''))
                : '';

            $conversionRows[] = [
                $conversion->created_at?->format('Y-m-d H:i:s') ?? '',
                number_format((float) $conversion->amount, 2, '.', ''),
                $conversion->destination_type,
                $destination ?? ('#'.$conversion->destination_id),
                $conversion->notes ?? '',
                $recordedBy ?: '—',
            ];
        }

        return [
            ['title' => 'Summary', 'rows' => $summaryRows],
            ['title' => 'Payments', 'rows' => $paymentRows],
            ['title' => 'Conversions', 'rows' => $conversionRows],
        ];
    }

    public function filename(Creditor $creditor): string
    {
        $slug = Str::slug($creditor->name) ?: 'creditor';

        return 'creditor_'.$creditor->id.'_'.$slug.'_'.now()->format('Y-m-d_His').'.xlsx';
    }
}
