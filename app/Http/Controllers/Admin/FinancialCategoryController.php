<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\IncomeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialCategoryController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.view'), 403);

        $expenseCategories = ExpenseCategory::query()
            ->withCount(['subcategories', 'transactions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $incomeCategories = IncomeCategory::query()
            ->withCount('transactions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.financial-categories.index', compact('expenseCategories', 'incomeCategories'));
    }

    public function createExpense(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.create'), 403);

        return view('admin.financial-categories.create-expense');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'code' => ['nullable', 'string', 'max:100', 'unique:expense_categories,code'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = ExpenseCategory::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ExpenseCategory::generateUniqueCode($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth('admin')->id(),
        ]);

        ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'code' => 'UNCLASSIFIED',
            'name' => 'Unclassified',
            'description' => 'Default subcategory for unclassified expenses',
            'is_active' => true,
            'created_by' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('admin.financial-categories.index')
            ->with('status', 'Expense category created successfully.');
    }

    public function editExpense(ExpenseCategory $expenseCategory): View
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.update'), 403);

        $expenseCategory->load(['subcategories' => fn ($query) => $query->orderBy('name')]);

        return view('admin.financial-categories.edit-expense', compact('expenseCategory'));
    }

    public function updateExpense(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.update'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name,'.$expenseCategory->id],
            'code' => ['required', 'string', 'max:100', 'unique:expense_categories,code,'.$expenseCategory->id],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $expenseCategory->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('admin.financial-categories.edit-expense', $expenseCategory)
            ->with('status', 'Expense category updated successfully.');
    }

    public function destroyExpense(ExpenseCategory $expenseCategory): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.delete'), 403);

        if ($expenseCategory->transactions()->exists()) {
            return redirect()
                ->route('admin.financial-categories.index')
                ->with('error', 'This expense category is linked to transactions. Deactivate it instead.');
        }

        $expenseCategory->delete();

        return redirect()
            ->route('admin.financial-categories.index')
            ->with('status', 'Expense category deleted successfully.');
    }

    public function createIncome(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.create'), 403);

        return view('admin.financial-categories.create-income');
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:income_categories,name'],
            'code' => ['nullable', 'string', 'max:100', 'unique:income_categories,code'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        IncomeCategory::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? IncomeCategory::generateUniqueCode($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
            'created_by' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('admin.financial-categories.index')
            ->with('status', 'Income category created successfully.');
    }

    public function editIncome(IncomeCategory $incomeCategory): View
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.update'), 403);

        return view('admin.financial-categories.edit-income', compact('incomeCategory'));
    }

    public function updateIncome(Request $request, IncomeCategory $incomeCategory): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.update'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:income_categories,name,'.$incomeCategory->id],
            'code' => ['required', 'string', 'max:100', 'unique:income_categories,code,'.$incomeCategory->id],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $incomeCategory->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('admin.financial-categories.edit-income', $incomeCategory)
            ->with('status', 'Income category updated successfully.');
    }

    public function destroyIncome(IncomeCategory $incomeCategory): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-categories.delete'), 403);

        if ($incomeCategory->is_system) {
            return redirect()
                ->route('admin.financial-categories.index')
                ->with('error', 'System income categories cannot be deleted.');
        }

        if ($incomeCategory->transactions()->exists()) {
            return redirect()
                ->route('admin.financial-categories.index')
                ->with('error', 'This income category is linked to transactions. Deactivate it instead.');
        }

        $incomeCategory->delete();

        return redirect()
            ->route('admin.financial-categories.index')
            ->with('status', 'Income category deleted successfully.');
    }
}
