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

class TransferController extends Controller
{
    /**
     * Display a listing of transfers.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('transfers.view'), 403);
        $transfers = FinancialTransaction::where('type', 'transfer')
            ->with(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.transfers.index', compact('transfers'));
    }

    /**
     * Show the form for creating a new transfer.
     */
    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('transfers.create'), 403);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        
        return view('admin.transfers.create', compact('banks', 'wallets'));
    }

    /**
     * Store a newly created transfer.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('transfers.create'), 403);
        $validated = $request->validate([
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source_type' => ['required', 'in:bank,wallet'],
            'source_id' => ['required', 'integer'],
            'destination_type' => ['required', 'in:bank,wallet'],
            'destination_id' => ['required', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        // Custom validation: source and destination must be different
        if ($validated['source_type'] === $validated['destination_type'] && 
            $validated['source_id'] === $validated['destination_id']) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['destination_id' => 'Source and destination must be different.']);
        }

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

            // Verify destination exists
            $destination = $validated['destination_type'] === 'bank'
                ? Bank::findOrFail($validated['destination_id'])
                : Wallet::findOrFail($validated['destination_id']);

            // Ensure same currency
            if ($source->currency !== $destination->currency) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Source and destination must have the same currency.');
            }

            // Check if approval is required
            $requiresApproval = config('approval.transfers.create', false);

            $transaction = FinancialTransaction::create([
                'transaction_number' => FinancialTransaction::generateTransactionNumber('transfer'),
                'transaction_date' => $validated['transaction_date'],
                'type' => 'transfer',
                'category' => 'transfer',
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'source_type' => $validated['source_type'],
                'source_id' => $validated['source_id'],
                'destination_type' => $validated['destination_type'],
                'destination_id' => $validated['destination_id'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth('admin')->id(),
                'approval_status' => $requiresApproval ? 'pending' : 'approved',
                'approved_by' => $requiresApproval ? null : auth('admin')->id(),
                'approved_at' => $requiresApproval ? null : now(),
            ]);

            // Only update balances if approved (or if approval not required)
            if (!$requiresApproval || $transaction->approval_status === 'approved') {
                $transaction->updateBalances();
            }

            DB::commit();

            $message = $requiresApproval 
                ? 'Transfer submitted successfully. It is pending approval.'
                : 'Transfer completed successfully.';

            return redirect()->route('admin.transfers.index')
                ->with('status', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to process transfer: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified transfer.
     */
    public function show(FinancialTransaction $transfer): View
    {
        abort_unless(auth('admin')->user()?->can('transfers.view'), 403);
        if ($transfer->type !== 'transfer') {
            abort(404);
        }

        $transfer->load(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet', 'creator', 'approver']);
        return view('admin.transfers.show', compact('transfer'));
    }

    /**
     * Approve a transfer.
     */
    public function approve(FinancialTransaction $transfer, Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('transfers.approve'), 403);
        try {
            abort_unless(auth('admin')->user()?->can('approvals.approve'), 403);
            abort_unless($transfer->type === 'transfer', 400, 'This is not a transfer.');
            abort_unless($transfer->approval_status === 'pending', 400, 'This transfer is not pending approval.');

            DB::beginTransaction();

            // Update balances now that it's approved
            $transfer->updateBalances();

            $transfer->update([
                'approval_status' => 'approved',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
            ]);

            DB::commit();

            return redirect()
                ->route('admin.transfers.show', $transfer)
                ->with('status', 'Transfer approved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('admin.transfers.show', $transfer)
                ->with('error', 'Failed to approve transfer: ' . $e->getMessage());
        }
    }

    /**
     * Reject a transfer.
     */
    public function reject(FinancialTransaction $transfer, Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('transfers.reject'), 403);
        try {
            abort_unless(auth('admin')->user()?->can('approvals.reject'), 403);
            abort_unless($transfer->type === 'transfer', 400, 'This is not a transfer.');
            abort_unless($transfer->approval_status === 'pending', 400, 'This transfer is not pending approval.');

            $transfer->update([
                'approval_status' => 'rejected',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
            ]);

            return redirect()
                ->route('admin.transfers.show', $transfer)
                ->with('status', 'Transfer rejected successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.transfers.show', $transfer)
                ->with('error', 'Failed to reject transfer: ' . $e->getMessage());
        }
    }
}
