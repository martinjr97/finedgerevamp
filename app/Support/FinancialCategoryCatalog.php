<?php

namespace App\Support;

use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\FinancialTransaction;
use App\Models\IncomeCategory;
use Illuminate\Support\Collection;

class FinancialCategoryCatalog
{
    /**
     * @return Collection<int, ExpenseCategory>
     */
    public static function activeExpenseCategories(): Collection
    {
        return ExpenseCategory::query()
            ->active()
            ->with(['subcategories' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, IncomeCategory>
     */
    public static function activeIncomeCategories(): Collection
    {
        return IncomeCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public static function expenseCategoryLabel(?string $code): string
    {
        if (! $code) {
            return '—';
        }

        $category = ExpenseCategory::query()->where('code', $code)->first();

        return $category?->name ?? ucwords(str_replace('_', ' ', $code));
    }

    public static function incomeCategoryLabel(?string $code): string
    {
        if (! $code) {
            return '—';
        }

        $category = IncomeCategory::query()->where('code', $code)->first();

        return $category?->name ?? ucwords(str_replace('_', ' ', $code));
    }

    public static function transactionCategoryLabel(FinancialTransaction $transaction): string
    {
        if ($transaction->type === 'income') {
            return self::incomeCategoryLabel($transaction->category);
        }

        if ($transaction->type === 'expense') {
            return self::expenseCategoryLabel($transaction->category);
        }

        return $transaction->category ? ucwords(str_replace('_', ' ', (string) $transaction->category)) : '—';
    }

    /**
     * @return array<int, string>
     */
    public static function activeExpenseCategoryCodes(): array
    {
        return self::activeExpenseCategories()->pluck('code')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function activeIncomeCategoryCodes(): array
    {
        return self::activeIncomeCategories()->pluck('code')->all();
    }

    public static function resolveExpenseCategory(?string $code): ?ExpenseCategory
    {
        return $code ? ExpenseCategory::query()->where('code', $code)->first() : null;
    }

    public static function resolveIncomeCategory(?string $code): ?IncomeCategory
    {
        return $code ? IncomeCategory::query()->where('code', $code)->first() : null;
    }

    public static function resolveExpenseSubcategory(int $categoryId, ?int $subcategoryId): ?ExpenseSubcategory
    {
        if (! $subcategoryId) {
            return null;
        }

        return ExpenseSubcategory::query()
            ->where('expense_category_id', $categoryId)
            ->whereKey($subcategoryId)
            ->first();
    }
}
