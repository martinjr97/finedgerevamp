<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\FinancialTransaction;
use App\Models\Wallet;
use App\Support\FinancialCategoryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialTransactionController extends Controller
{
    /**
     * Display a listing of financial transactions.
     */
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.view'), 403);
        $query = FinancialTransaction::with(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $transactions = $query->paginate(50);

        $expenseCategories = FinancialCategoryCatalog::activeExpenseCategories();
        $incomeCategories = FinancialCategoryCatalog::activeIncomeCategories();

        return view('admin.financial-transactions.index', compact('transactions', 'expenseCategories', 'incomeCategories'));
    }

    /**
     * Show the form for creating a new income transaction.
     */
    public function createIncome(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        $incomeCategories = FinancialCategoryCatalog::activeIncomeCategories();
        
        return view('admin.financial-transactions.create-income', compact('banks', 'wallets', 'incomeCategories'));
    }

    /**
     * Show the form for creating a new expense transaction.
     */
    public function createExpense(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        $expenseCategories = FinancialCategoryCatalog::activeExpenseCategories();
        $employees = Employee::query()->active()->orderBy('first_name')->orderBy('last_name')->get();
        
        return view('admin.financial-transactions.create-expense', compact('banks', 'wallets', 'expenseCategories', 'employees'));
    }

    /**
     * Store a newly created income transaction.
     */
    public function storeIncome(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);

        $incomeCategoryCodes = FinancialCategoryCatalog::activeIncomeCategoryCodes();
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', $incomeCategoryCodes)],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'destination_type' => ['required', 'in:bank,wallet'],
            'destination_id' => ['required', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            // Verify destination exists
            $destination = $validated['destination_type'] === 'bank'
                ? Bank::findOrFail($validated['destination_id'])
                : Wallet::findOrFail($validated['destination_id']);

            $incomeCategory = FinancialCategoryCatalog::resolveIncomeCategory($validated['category']);

            $transaction = FinancialTransaction::create([
                'transaction_number' => FinancialTransaction::generateTransactionNumber('income'),
                'transaction_date' => $validated['transaction_date'],
                'type' => 'income',
                'category' => $validated['category'],
                'income_category_id' => $incomeCategory?->id,
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'destination_type' => $validated['destination_type'],
                'destination_id' => $validated['destination_id'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
            ]);

            // Update balances
            $transaction->updateBalances();

            DB::commit();

            return redirect()->route('admin.financial-transactions.index')
                ->with('status', 'Income transaction recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to record income transaction: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created expense transaction.
     */
    public function storeExpense(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);

        $expenseCategoryCodes = FinancialCategoryCatalog::activeExpenseCategoryCodes();
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', $expenseCategoryCodes)],
            'expense_subcategory_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:500'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source_type' => ['required', 'in:bank,wallet'],
            'source_id' => ['required', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            // Verify source exists and has sufficient balance
            $source = $validated['source_type'] === 'bank'
                ? Bank::findOrFail($validated['source_id'])
                : Wallet::findOrFail($validated['source_id']);

            if ($source->current_balance < $validated['amount']) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Insufficient balance. Available: ' . number_format($source->current_balance, 2));
            }

            $expenseCategory = FinancialCategoryCatalog::resolveExpenseCategory($validated['category']);
            $expenseSubcategory = $expenseCategory
                ? FinancialCategoryCatalog::resolveExpenseSubcategory($expenseCategory->id, $validated['expense_subcategory_id'] ?? null)
                : null;

            $employee = ! empty($validated['employee_id'])
                ? Employee::query()->find($validated['employee_id'])
                : null;
            $receiverName = trim((string) ($validated['receiver_name'] ?? ''));
            if ($receiverName === '' && $employee) {
                $receiverName = $employee->full_name;
            }

            $transaction = FinancialTransaction::create([
                'transaction_number' => FinancialTransaction::generateTransactionNumber('expense'),
                'transaction_date' => $validated['transaction_date'],
                'type' => 'expense',
                'category' => $validated['category'],
                'expense_category_id' => $expenseCategory?->id,
                'expense_subcategory_id' => $expenseSubcategory?->id,
                'description' => $validated['description'],
                'receiver_name' => $receiverName !== '' ? $receiverName : null,
                'employee_id' => $employee?->id,
                'amount' => $validated['amount'],
                'source_type' => $validated['source_type'],
                'source_id' => $validated['source_id'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
            ]);

            // Update balances
            $transaction->updateBalances();

            DB::commit();

            return redirect()->route('admin.financial-transactions.index')
                ->with('status', 'Expense transaction recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to record expense transaction: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show(FinancialTransaction $financialTransaction): View
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.view'), 403);
        $financialTransaction->load(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet', 'creator', 'employee', 'expenseCategory', 'expenseSubcategory']);
        return view('admin.financial-transactions.show', compact('financialTransaction'));
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(FinancialTransaction $financialTransaction): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.delete'), 403);
        try {
            DB::beginTransaction();

            // Reverse the balance updates
            if ($financialTransaction->type === 'income' && $financialTransaction->destination_type && $financialTransaction->destination_id) {
                $destination = $financialTransaction->destination_type === 'bank'
                    ? Bank::find($financialTransaction->destination_id)
                    : Wallet::find($financialTransaction->destination_id);
                
                if ($destination) {
                    $destination->updateBalance($financialTransaction->amount, 'debit');
                }
            } elseif ($financialTransaction->type === 'expense' && $financialTransaction->source_type && $financialTransaction->source_id) {
                $source = $financialTransaction->source_type === 'bank'
                    ? Bank::find($financialTransaction->source_id)
                    : Wallet::find($financialTransaction->source_id);
                
                if ($source) {
                    $source->updateBalance($financialTransaction->amount, 'credit');
                }
            } elseif ($financialTransaction->type === 'transfer') {
                // Reverse transfer
                $source = $financialTransaction->source_type === 'bank'
                    ? Bank::find($financialTransaction->source_id)
                    : Wallet::find($financialTransaction->source_id);
                
                $destination = $financialTransaction->destination_type === 'bank'
                    ? Bank::find($financialTransaction->destination_id)
                    : Wallet::find($financialTransaction->destination_id);
                
                if ($source) {
                    $source->updateBalance($financialTransaction->amount, 'credit');
                }
                
                if ($destination) {
                    $destination->updateBalance($financialTransaction->amount, 'debit');
                }
            }

            $financialTransaction->delete();

            DB::commit();

            return redirect()->route('admin.financial-transactions.index')
                ->with('status', 'Transaction deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete transaction: ' . $e->getMessage());
        }
    }
}
