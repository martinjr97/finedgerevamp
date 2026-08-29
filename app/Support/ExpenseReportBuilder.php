<?php

namespace App\Support;

use App\Models\ExpenseCategory;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExpenseReportBuilder
{
    /**
     * @return array{
     *     expenses: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *     summary: array<string, float|int|string|null>,
     *     categoryBreakdown: Collection<int, object>,
     *     subcategoryBreakdown: Collection<int, object>,
     *     topExpenses: Collection<int, FinancialTransaction>,
     *     topReceivers: Collection<int, object>
     * }
     */
    public function build(Request $request): array
    {
        $baseQuery = $this->applyFilters(
            FinancialTransaction::query()->where('type', 'expense'),
            $request,
        );

        $filteredIds = (clone $baseQuery)->select('financial_transactions.id');
        $totalAmount = (float) (clone $baseQuery)->sum('financial_transactions.amount');
        $transactionCount = (int) (clone $baseQuery)->count();

        $categoryBreakdown = FinancialTransaction::query()
            ->whereIn('financial_transactions.id', $filteredIds)
            ->join('expense_categories', 'expense_categories.id', '=', 'financial_transactions.expense_category_id')
            ->selectRaw('expense_categories.id as category_id, expense_categories.name as category_name, expense_categories.code as category_code, COUNT(*) as transaction_count, SUM(financial_transactions.amount) as total_amount')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.code')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) use ($totalAmount) {
                $row->share_percentage = $totalAmount > 0
                    ? round(((float) $row->total_amount / $totalAmount) * 100, 1)
                    : 0.0;

                return $row;
            });

        $subcategoryBreakdown = FinancialTransaction::query()
            ->whereIn('financial_transactions.id', $filteredIds)
            ->leftJoin('expense_subcategories', 'expense_subcategories.id', '=', 'financial_transactions.expense_subcategory_id')
            ->selectRaw("COALESCE(expense_subcategories.name, 'Unclassified') as subcategory_name, COUNT(*) as transaction_count, SUM(financial_transactions.amount) as total_amount")
            ->groupBy('subcategory_name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $topReceivers = FinancialTransaction::query()
            ->whereIn('financial_transactions.id', $filteredIds)
            ->whereNotNull('receiver_name')
            ->where('receiver_name', '!=', '')
            ->selectRaw('receiver_name, COUNT(*) as transaction_count, SUM(amount) as total_amount')
            ->groupBy('receiver_name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $topCategory = $categoryBreakdown->first();

        $expenses = (clone $baseQuery)
            ->with(['expenseCategory', 'expenseSubcategory', 'employee', 'sourceBank', 'sourceWallet', 'creator'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $topExpenses = (clone $baseQuery)
            ->with(['expenseCategory', 'expenseSubcategory', 'employee'])
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return [
            'expenses' => $expenses,
            'summary' => [
                'total_amount' => $totalAmount,
                'transaction_count' => $transactionCount,
                'average_amount' => $transactionCount > 0 ? round($totalAmount / $transactionCount, 2) : 0.0,
                'top_category_name' => $topCategory->category_name ?? null,
                'top_category_amount' => isset($topCategory->total_amount) ? (float) $topCategory->total_amount : 0.0,
            ],
            'categoryBreakdown' => $categoryBreakdown,
            'subcategoryBreakdown' => $subcategoryBreakdown,
            'topExpenses' => $topExpenses,
            'topReceivers' => $topReceivers,
        ];
    }

    public function exportRows(Request $request): Collection
    {
        return $this->applyFilters(
            FinancialTransaction::query()->where('type', 'expense'),
            $request,
        )
            ->with(['expenseCategory', 'expenseSubcategory', 'employee', 'sourceBank', 'sourceWallet'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', (int) $request->input('expense_category_id'));
        }

        if ($request->filled('expense_subcategory_id')) {
            $query->where('expense_subcategory_id', (int) $request->input('expense_subcategory_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }

        if ($request->filled('receiver_name')) {
            $query->where('receiver_name', 'like', '%'.$request->input('receiver_name').'%');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('description', 'like', "%{$search}%")
                    ->orWhere('transaction_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('receiver_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    public function filterCategories(): Collection
    {
        return ExpenseCategory::query()
            ->with(['subcategories' => fn ($query) => $query->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
