<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Creditor;
use App\Models\CreditorConversion;
use App\Models\FinancialTransaction;
use App\Models\IncomeCategory;
use App\Models\Wallet;
use App\Services\CreditorBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditorController extends Controller
{
    public function __construct(
        private readonly CreditorBalanceService $creditorBalanceService,
    ) {}

    /**
     * Display a listing of creditors.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.view'), 403);
        $creditors = Creditor::where('is_active', true)->orderBy('due_date')->get();

        return view('admin.creditors.index', compact('creditors'));
    }

    /**
     * Show the form for creating a new creditor.
     */
    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.create'), 403);

        return view('admin.creditors.create');
    }

    /**
     * Store a newly created creditor.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.create'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        Creditor::create($validated);

        return redirect()->route('admin.creditors.index')
            ->with('status', 'Creditor created successfully.');
    }

    /**
     * Display the specified creditor.
     */
    public function show(Creditor $creditor): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.view'), 403);

        $creditor->load([
            'expenseTransactions.sourceBank',
            'expenseTransactions.sourceWallet',
            'expenseTransactions.creator',
            'conversions.destinationBank',
            'conversions.destinationWallet',
            'conversions.createdBy',
        ]);

        $payments = $creditor->expenseTransactions;
        $totalPayments = (float) $payments->sum('amount');
        $banks = Bank::query()->where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.creditors.show', compact('creditor', 'payments', 'totalPayments', 'banks', 'wallets'));
    }

    /**
     * Convert creditor balance to a bank or wallet (reduce liability, credit asset).
     */
    public function convert(Request $request, Creditor $creditor): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.update'), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'destination_type' => ['required', 'in:bank,wallet'],
            'destination_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $amount = (float) $validated['amount'];
        if ($this->creditorBalanceService->paymentExceedsBalance($creditor, $amount)) {
            return back()->withInput()
                ->withErrors(['amount' => 'Amount cannot exceed creditor balance (ZMW '.number_format((float) $creditor->amount, 2).').']);
        }

        $destination = $validated['destination_type'] === 'bank'
            ? Bank::query()->where('is_active', true)->find($validated['destination_id'])
            : Wallet::query()->where('is_active', true)->find($validated['destination_id']);

        if (! $destination) {
            return back()->withInput()
                ->withErrors(['destination_id' => 'Selected account does not exist or is inactive.']);
        }

        $incomeCategory = IncomeCategory::query()
            ->where('code', 'creditor_conversion')
            ->first();

        if (! $incomeCategory) {
            return back()->withInput()
                ->withErrors(['error' => 'Creditor conversion income category is not configured. Run FinancialCategorySeeder.']);
        }

        try {
            DB::beginTransaction();

            $description = 'Creditor conversion: '.$creditor->name.(
                filled($validated['notes'] ?? null) ? ' — '.$validated['notes'] : ''
            );

            $transaction = FinancialTransaction::create([
                'transaction_number' => FinancialTransaction::generateTransactionNumber('income'),
                'transaction_date' => now()->toDateString(),
                'type' => 'income',
                'category' => $incomeCategory->code,
                'income_category_id' => $incomeCategory->id,
                'description' => $description,
                'amount' => $amount,
                'destination_type' => $validated['destination_type'],
                'destination_id' => (int) $validated['destination_id'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
                'approval_status' => 'approved',
                'metadata' => [
                    'creditor_conversion' => true,
                    'creditor_id' => $creditor->id,
                ],
            ]);

            $transaction->updateBalances();

            CreditorConversion::create([
                'creditor_id' => $creditor->id,
                'amount' => $amount,
                'destination_type' => strtoupper($validated['destination_type']),
                'destination_id' => (int) $validated['destination_id'],
                'financial_transaction_id' => $transaction->id,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
            ]);

            $this->creditorBalanceService->reduceBalance($creditor, $amount);

            DB::commit();

            return redirect()->route('admin.creditors.show', $creditor)
                ->with('status', 'Creditor balance converted successfully. ZMW '.number_format($amount, 2).' credited to '.$destination->name.'.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()
                ->withErrors(['error' => 'Conversion failed: '.$e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified creditor.
     */
    public function edit(Creditor $creditor): View
    {
        abort_unless(auth('admin')->user()?->can('creditors.update'), 403);

        return view('admin.creditors.edit', compact('creditor'));
    }

    /**
     * Update the specified creditor.
     */
    public function update(Request $request, Creditor $creditor): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.update'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $creditor->update($validated);

        return redirect()->route('admin.creditors.index')
            ->with('status', 'Creditor updated successfully.');
    }

    /**
     * Remove the specified creditor.
     */
    public function destroy(Creditor $creditor): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('creditors.delete'), 403);
        $creditor->delete();

        return redirect()->route('admin.creditors.index')
            ->with('status', 'Creditor deleted successfully.');
    }
}
