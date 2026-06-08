<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\FinancialTransaction;
use App\Models\Wallet;
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

        return view('admin.financial-transactions.index', compact('transactions'));
    }

    /**
     * Show the form for creating a new income transaction.
     */
    public function createIncome(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        
        return view('admin.financial-transactions.create-income', compact('banks', 'wallets'));
    }

    /**
     * Show the form for creating a new expense transaction.
     */
    public function createExpense(): View
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        
        return view('admin.financial-transactions.create-expense', compact('banks', 'wallets'));
    }

    /**
     * Store a newly created income transaction.
     */
    public function storeIncome(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('financial-transactions.create'), 403);
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'category' => ['required', 'in:loan_interest,loan_processing_fee,shareholder_contribution,investment_income,donation,grant,other_income'],
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

            $transaction = FinancialTransaction::create([
                'transaction_number' => FinancialTransaction::generateTransactionNumber('income'),
                'transaction_date' => $validated['transaction_date'],
                'type' => 'income',
                'category' => $validated['category'],
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
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'category' => ['required', 'in:operational,administrative,marketing,salaries,utilities,rent,other_expense'],
            'description' => ['required', 'string', 'max:500'],
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

            $transaction = FinancialTransaction::create([
                'transaction_number' => FinancialTransaction::generateTransactionNumber('expense'),
                'transaction_date' => $validated['transaction_date'],
                'type' => 'expense',
                'category' => $validated['category'],
                'description' => $validated['description'],
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
        $financialTransaction->load(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet', 'creator']);
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
