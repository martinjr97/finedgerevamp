<?php

namespace App\Support;

use App\Models\Creditor;
use App\Models\FinancialTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CreditorReportBuilder
{
    /**
     * @return array{
     *     summary: array<string, float|int|string|null>,
     *     creditors: Collection<int, object>,
     *     payments: LengthAwarePaginator,
     *     filters: array{date_from: string, date_to: string, status: string, search: ?string}
     * }
     */
    public function build(Request $request): array
    {
        $filters = $this->resolveFilters($request);
        $creditors = $this->creditorRows($filters);
        $paidInPeriod = $this->paidInPeriodByCreditor($filters);

        $creditorRows = $creditors->map(function (Creditor $creditor) use ($paidInPeriod) {
            $paid = $paidInPeriod->get($creditor->id);

            return (object) [
                'id' => $creditor->id,
                'name' => $creditor->name,
                'description' => $creditor->description,
                'outstanding' => (float) $creditor->amount,
                'due_date' => $creditor->due_date,
                'is_active' => (bool) $creditor->is_active,
                'paid_in_period' => (float) ($paid->total_paid ?? 0),
                'payment_count_in_period' => (int) ($paid->payment_count ?? 0),
            ];
        });

        $summary = $this->buildSummary($creditorRows, $filters);

        $payments = $this->paymentsQuery($filters)
            ->with(['creditor', 'sourceBank', 'sourceWallet', 'expenseCategory'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return [
            'summary' => $summary,
            'creditors' => $creditorRows,
            'payments' => $payments,
            'filters' => $filters,
        ];
    }

    /**
     * @return array<int, array{title: string, rows: array<int, array<int, string|float|int|null>>}>
     */
    public function exportSheets(Request $request): array
    {
        $report = $this->build($request);
        $filters = $report['filters'];
        $summary = $report['summary'];

        $summarySheet = [
            ['Metric', 'Value'],
            ['Report period from', $filters['date_from']],
            ['Report period to', $filters['date_to']],
            ['Creditor status filter', ucfirst($filters['status'])],
            ['Total creditors', $summary['creditor_count']],
            ['Creditors with balance', $summary['creditors_with_balance']],
            ['Total outstanding (ZMW)', number_format((float) $summary['total_outstanding'], 2, '.', '')],
            ['Total paid in period (ZMW)', number_format((float) $summary['total_paid_in_period'], 2, '.', '')],
            ['Payment transactions in period', $summary['payment_count_in_period']],
        ];

        $creditorRows = [
            ['Creditor', 'Status', 'Outstanding (ZMW)', 'Paid in period (ZMW)', 'Payments in period', 'Due date', 'Description'],
        ];
        foreach ($report['creditors'] as $row) {
            $creditorRows[] = [
                $row->name,
                $row->is_active ? 'Active' : 'Inactive',
                number_format($row->outstanding, 2, '.', ''),
                number_format($row->paid_in_period, 2, '.', ''),
                $row->payment_count_in_period,
                $row->due_date?->format('Y-m-d') ?? '',
                $row->description ?? '',
            ];
        }

        $paymentRows = [
            ['Date', 'Transaction #', 'Creditor', 'Amount (ZMW)', 'Description', 'Category', 'Source', 'Reference'],
        ];

        $this->paymentsQuery($filters)
            ->with(['creditor', 'sourceBank', 'sourceWallet', 'expenseCategory'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->chunk(500, function ($chunk) use (&$paymentRows) {
                foreach ($chunk as $payment) {
                    $source = $payment->sourceBank?->name ?? $payment->sourceWallet?->name ?? '';
                    $paymentRows[] = [
                        $payment->transaction_date?->format('Y-m-d') ?? '',
                        $payment->transaction_number,
                        $payment->creditor?->name ?? '',
                        number_format((float) $payment->amount, 2, '.', ''),
                        $payment->description,
                        FinancialCategoryCatalog::transactionCategoryLabel($payment),
                        $source,
                        $payment->reference_number ?? '',
                    ];
                }
            });

        return [
            ['title' => 'Summary', 'rows' => $summarySheet],
            ['title' => 'Creditors', 'rows' => $creditorRows],
            ['title' => 'Payments', 'rows' => $paymentRows],
        ];
    }

    /**
     * @return array{date_from: string, date_to: string, status: string, search: ?string}
     */
    private function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $dateFrom = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $dateTo = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->toDateString()
            : now()->toDateString();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $validated['status'] ?? 'active',
            'search' => isset($validated['search']) ? trim($validated['search']) : null,
        ];
    }

    /**
     * @param  array{date_from: string, date_to: string, status: string, search: ?string}  $filters
     * @return Collection<int, Creditor>
     */
    private function creditorRows(array $filters): Collection
    {
        return $this->creditorsQuery($filters)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{date_from: string, date_to: string, status: string, search: ?string}  $filters
     */
    private function creditorsQuery(array $filters): Builder
    {
        $query = Creditor::query();

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array{date_from: string, date_to: string, status: string, search: ?string}  $filters
     */
    private function paymentsQuery(array $filters): Builder
    {
        $creditorIds = $this->creditorsQuery($filters)->select('id');

        return FinancialTransaction::query()
            ->where('type', 'expense')
            ->whereNotNull('creditor_id')
            ->whereIn('creditor_id', $creditorIds)
            ->whereDate('transaction_date', '>=', $filters['date_from'])
            ->whereDate('transaction_date', '<=', $filters['date_to']);
    }

    /**
     * @param  array{date_from: string, date_to: string, status: string, search: ?string}  $filters
     * @return Collection<int, object>
     */
    private function paidInPeriodByCreditor(array $filters): Collection
    {
        return $this->paymentsQuery($filters)
            ->selectRaw('creditor_id, COUNT(*) as payment_count, SUM(amount) as total_paid')
            ->groupBy('creditor_id')
            ->get()
            ->keyBy('creditor_id');
    }

    /**
     * @param  Collection<int, object>  $creditorRows
     * @param  array{date_from: string, date_to: string, status: string, search: ?string}  $filters
     * @return array<string, float|int|string|null>
     */
    private function buildSummary(Collection $creditorRows, array $filters): array
    {
        $totalOutstanding = (float) $creditorRows->sum('outstanding');
        $creditorsWithBalance = (int) $creditorRows->filter(fn ($row) => $row->outstanding > 0.00001)->count();

        $paymentsQuery = $this->paymentsQuery($filters);
        $totalPaidInPeriod = (float) (clone $paymentsQuery)->sum('amount');
        $paymentCountInPeriod = (int) (clone $paymentsQuery)->count();

        return [
            'creditor_count' => $creditorRows->count(),
            'creditors_with_balance' => $creditorsWithBalance,
            'total_outstanding' => $totalOutstanding,
            'total_paid_in_period' => $totalPaidInPeriod,
            'payment_count_in_period' => $paymentCountInPeriod,
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];
    }
}
