<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RepaymentRecoveryMethod;
use App\Support\ZambianPhoneRules;
use App\Models\Bank;
use App\Models\CashRegister;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Wallet;
use App\Services\CashRegisterService;
use App\Services\CustomerNotificationService;
use App\Services\LoanRepaymentLedgerService;
use App\Services\RepaymentProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RepaymentController extends Controller
{
    private const OVERPAYMENT_REASONS = [
        'Duplicate deduction',
        'Customer paid extra',
        'Gateway over-collected',
        'Manual reconciliation',
        'Other',
    ];

    public function __construct(
        private readonly RepaymentProcessingService $repaymentProcessingService,
        private readonly CustomerNotificationService $customerNotificationService,
        private readonly LoanRepaymentLedgerService $ledgerService,
        private readonly CashRegisterService $cashRegisterService
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('repayments.view'), 403);

        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan.loanProduct']);

        // Filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('channel_id') && $request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('processed_date_from') && $request->processed_date_from) {
            $query->whereDate('processed_at', '>=', $request->processed_date_from);
        }

        if ($request->has('processed_date_to') && $request->processed_date_to) {
            $query->whereDate('processed_at', '<=', $request->processed_date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('repayment_number', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhere('external_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $repayments = $query->latest('created_at')->paginate(20);

        $channels = Channel::where('is_active', true)->where('can_repay', true)->orderBy('name')->get();
        $customers = Customer::orderBy('first_name')->orderBy('last_name')->limit(100)->get();

        return view('admin.repayments.index', compact('repayments', 'channels', 'customers'));
    }

    public function export(Request $request)
    {
        abort_unless(auth('admin')->user()?->can('repayments.export'), 403);

        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('channel_id') && $request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('processed_date_from') && $request->processed_date_from) {
            $query->whereDate('processed_at', '>=', $request->processed_date_from);
        }

        if ($request->has('processed_date_to') && $request->processed_date_to) {
            $query->whereDate('processed_at', '<=', $request->processed_date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('repayment_number', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhere('external_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $repayments = $query->latest('created_at')->get();

        $filename = 'repayments_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($repayments) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Repayment Number',
                'Date',
                'Customer Name',
                'Customer Email',
                'Customer Phone',
                'Channel',
                'Total Amount',
                'Phone Number Used',
                'External Reference',
                'External Transaction ID',
                'Status',
                'Processed At',
                'Loan Numbers',
                'Total Principal',
                'Total Interest',
                'Total Processing Fee',
                'Created At',
            ]);

            foreach ($repayments as $repayment) {
                $loanNumbers = $repayment->loanRepayments->pluck('loan.loan_number')->implode(', ');
                $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
                $totalInterest = $repayment->loanRepayments->sum('interest_amount');
                $totalProcessingFee = $repayment->loanRepayments->sum('processing_fee_amount');

                fputcsv($file, [
                    $repayment->repayment_number,
                    $repayment->created_at->format('Y-m-d'),
                    $repayment->customer->full_name ?? 'N/A',
                    $repayment->customer->email ?? 'N/A',
                    $repayment->customer->phone ?? 'N/A',
                    $repayment->channel->name ?? 'N/A',
                    number_format($repayment->total_amount, 2),
                    $repayment->phone_number ?? 'N/A',
                    $repayment->external_reference ?? 'N/A',
                    $repayment->external_transaction_id ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $repayment->status)),
                    $repayment->processed_at ? $repayment->processed_at->format('Y-m-d H:i:s') : 'N/A',
                    $loanNumbers ?: 'N/A',
                    number_format($totalPrincipal, 2),
                    number_format($totalInterest, 2),
                    number_format($totalProcessingFee, 2),
                    $repayment->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function show(Repayment $repayment): View
    {
        abort_unless(auth('admin')->user()?->can('repayments.view'), 403);

        $repayment->load([
            'customer',
            'channel',
            'loanRepayments.loan.loanProduct',
            'loanRepayments.loan.customerGroup',
            'loanRepayments.loan.customer',
        ]);

        $banks = Bank::query()->where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::query()->where('is_active', true)->orderBy('name')->get();
        $this->cashRegisterService->defaultRegister();
        $cashRegisters = CashRegister::query()->where('is_active', true)->orderBy('name')->get();
        $channels = Channel::query()
            ->where('is_active', true)
            ->where('can_repay', true)
            ->orderBy('name')
            ->get();

        return view('admin.repayments.show', compact('repayment', 'banks', 'wallets', 'cashRegisters', 'channels'));
    }

    public function createForCustomer(Request $request, Customer $customer): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('repayments.create'), 403);

        $activeLoans = $customer->loans()
            ->whereIn('status', ['approved', 'active'])
            ->with('loanProduct')
            ->orderBy('loan_start_date')
            ->get();

        if ($activeLoans->isEmpty()) {
            return redirect()->route('admin.customers.show', $customer)
                ->with('error', 'This customer has no active loans to repay.');
        }

        $channels = Channel::query()
            ->where('is_active', true)
            ->where('can_repay', true)
            ->orderBy('name')
            ->get();

        if ($channels->isEmpty()) {
            return redirect()->route('admin.customers.show', $customer)
                ->with('error', 'No active repayment channels are configured.');
        }

        $banks = Bank::query()->where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::query()->where('is_active', true)->orderBy('name')->get();
        $cashRegisters = CashRegister::query()->where('is_active', true)->orderBy('name')->get();
        $this->cashRegisterService->defaultRegister();

        $totals = [
            'outstanding' => $customer->getTotalOutstandingBalance(),
            'overdue' => $customer->getTotalOverdueAmount(),
        ];

        $preselectedLoan = null;
        if ($request->filled('loan_id')) {
            $preselectedLoan = $activeLoans->firstWhere('id', (int) $request->input('loan_id'));
        }

        $loanLedgerById = $activeLoans->mapWithKeys(function (Loan $loan): array {
            $netPaid = $this->ledgerService->calculateNetPaid($loan);
            $expected = $this->ledgerService->getExpectedSettlementAmount($loan);

            return [
                $loan->id => [
                    'outstanding' => $this->ledgerService->calculateOutstandingBalance($loan, $netPaid),
                    'expected_settlement' => $expected,
                    'net_paid' => $netPaid,
                ],
            ];
        });

        $returnToLoanUrl = $preselectedLoan
            ? route('admin.loans.show', $preselectedLoan)
            : null;

        return view('admin.repayments.create-for-customer', [
            'customer' => $customer,
            'activeLoans' => $activeLoans,
            'channels' => $channels,
            'banks' => $banks,
            'wallets' => $wallets,
            'cashRegisters' => $cashRegisters,
            'totals' => $totals,
            'preselectedLoan' => $preselectedLoan,
            'returnToLoanUrl' => $returnToLoanUrl,
            'loanLedgerById' => $loanLedgerById,
            'overpaymentReasons' => self::OVERPAYMENT_REASONS,
            'recoveryMethods' => RepaymentRecoveryMethod::labels(),
        ]);
    }

    public function storeForCustomer(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('repayments.create'), 403);

        $validated = $request->validate([
            'repayment_type' => 'required|in:partial,overdue,full',
            'loan_id' => 'nullable|required_if:repayment_type,partial|exists:loans,id',
            'amount' => 'nullable|required_if:repayment_type,partial|numeric|min:0.01',
            'channel_id' => 'required|exists:channels,id',
            'phone_number' => ZambianPhoneRules::nullable(),
            'submission_mode' => 'required|in:auto,manual',
            'manual_source' => 'nullable|in:bank,wallet,cash',
            'bank_id' => 'nullable|required_if:manual_source,bank|exists:banks,id',
            'wallet_id' => 'nullable|required_if:manual_source,wallet|exists:wallets,id',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
            'external_reference' => 'nullable|string|max:255',
            'external_transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'overpayment_reason' => ['nullable', Rule::in(self::OVERPAYMENT_REASONS)],
            'overpayment_reason_other' => 'nullable|string|max:500',
            'overpayment_confirmed' => 'nullable|in:1',
            'recovery_method' => ['required', Rule::in(RepaymentRecoveryMethod::values())],
        ]);

        $selectedLoan = null;
        $isOverpayment = false;
        if ($validated['repayment_type'] === 'partial') {
            $selectedLoan = Loan::query()
                ->where('id', $validated['loan_id'])
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['approved', 'active'])
                ->first();

            if (! $selectedLoan) {
                return back()->withInput()->withErrors([
                    'loan_id' => 'The selected loan is not eligible for repayment.',
                ]);
            }

            $submittedAmount = (float) $validated['amount'];
            $outstandingBalance = $this->ledgerService->calculateOutstandingBalance($selectedLoan);
            $isOverpayment = $submittedAmount > ($outstandingBalance + 0.0001);

            if ($isOverpayment) {
                $overpaymentErrors = [];
                if (empty($validated['overpayment_reason'])) {
                    $overpaymentErrors['overpayment_reason'] = 'Please select a reason for the overpayment.';
                }
                if (($validated['overpayment_reason'] ?? '') === 'Other' && blank($validated['overpayment_reason_other'] ?? null)) {
                    $overpaymentErrors['overpayment_reason_other'] = 'Please describe the overpayment reason.';
                }
                if (empty($validated['overpayment_confirmed'])) {
                    $overpaymentErrors['overpayment_confirmed'] = 'Please confirm this overpayment before submitting.';
                }
                if ($overpaymentErrors !== []) {
                    return back()->withInput()->withErrors($overpaymentErrors);
                }
            }
        }

        $repaymentAmount = $this->calculateRepaymentAmount(
            $customer,
            $validated['repayment_type'],
            $selectedLoan,
            isset($validated['amount']) ? (float) $validated['amount'] : null
        );

        if ($repaymentAmount <= 0) {
            return back()->withInput()->withErrors([
                'amount' => 'The repayment amount must be greater than zero.',
            ]);
        }

        $channel = Channel::query()
            ->where('id', $validated['channel_id'])
            ->where('is_active', true)
            ->where('can_repay', true)
            ->firstOrFail();

        $isManualFlow = ($validated['submission_mode'] === 'manual') || ! ((bool) $channel->is_repayment_integrated);
        $manualSource = $isManualFlow ? ($validated['manual_source'] ?? null) : null;
        [$receivedViaType, $receivedViaId] = $this->resolveReceivedVia(
            $manualSource,
            isset($validated['bank_id']) ? (int) $validated['bank_id'] : null,
            isset($validated['wallet_id']) ? (int) $validated['wallet_id'] : null,
            isset($validated['cash_register_id']) ? (int) $validated['cash_register_id'] : null
        );

        $metadata = [
            'repayment_type' => $validated['repayment_type'],
            'loan_id' => $selectedLoan?->id,
            'submission_mode' => $isManualFlow ? 'manual' : 'auto',
            'submitted_from' => 'admin_portal',
            'submitted_by_admin_id' => auth('admin')->id(),
            'submitted_at' => now()->toIso8601String(),
            'manual_source' => $manualSource,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($isOverpayment && $selectedLoan) {
            $submittedAmount = (float) $validated['amount'];
            $outstandingBalance = $this->ledgerService->calculateOutstandingBalance($selectedLoan);
            $expectedSettlement = $this->ledgerService->getExpectedSettlementAmount($selectedLoan);
            $netPaid = $this->ledgerService->calculateNetPaid($selectedLoan);
            $reasonLabel = ($validated['overpayment_reason'] ?? '') === 'Other'
                ? (string) ($validated['overpayment_reason_other'] ?? 'Other')
                : (string) ($validated['overpayment_reason'] ?? '');

            $metadata['overpayment'] = [
                'outstanding_at_submit' => round($outstandingBalance, 2),
                'expected_settlement' => round($expectedSettlement, 2),
                'net_paid_before' => round($netPaid, 2),
                'amount_entered' => round($submittedAmount, 2),
                'amount_applied_to_loan' => round(min($submittedAmount, $outstandingBalance), 2),
                'excess_above_outstanding' => round(max(0, $submittedAmount - $outstandingBalance), 2),
                'projected_customer_credit' => round(max(0, $netPaid + $submittedAmount - $expectedSettlement), 2),
                'reason' => $reasonLabel,
                'confirmed_at' => now()->toIso8601String(),
            ];
            $metadata['notes'] = trim(collect([
                $validated['notes'] ?? null,
                'Overpayment / suspense reason: '.$reasonLabel,
            ])->filter()->implode("\n"));
        }

        try {
            DB::beginTransaction();

            $repayment = Repayment::create([
                'customer_id' => $customer->id,
                'channel_id' => $channel->id,
                'repayment_number' => Repayment::generateRepaymentNumber(),
                'total_amount' => $repaymentAmount,
                'recovery_method' => $validated['recovery_method'],
                'phone_number' => $validated['phone_number'] ?? $customer->phone,
                'external_reference' => $validated['external_reference'] ?? null,
                'external_transaction_id' => $validated['external_transaction_id'] ?? null,
                'status' => $isManualFlow ? 'pending' : 'processing',
                'status_message' => $isManualFlow
                    ? 'Repayment submitted and awaiting approval.'
                    : 'Repayment submitted for automated processing.',
                'received_via_type' => $receivedViaType,
                'received_via_id' => $receivedViaId,
                'metadata' => $metadata,
            ]);

            if ($isManualFlow) {
                DB::commit();

                return redirect()
                    ->route('admin.repayments.show', $repayment)
                    ->with('status', 'Repayment submitted successfully and is pending approval.');
            }

            $paymentResult = $this->repaymentProcessingService->processPayment(
                $repaymentAmount,
                $channel,
                $validated['phone_number'] ?? $customer->phone
            );

            if (! $paymentResult['success']) {
                $repayment->update([
                    'status' => 'failed',
                    'status_message' => $paymentResult['message'] ?? 'Payment processing failed.',
                    'metadata' => array_merge($metadata, [
                        'gateway_response' => $paymentResult,
                        'failed_at' => now()->toIso8601String(),
                    ]),
                ]);

                DB::commit();

                return redirect()
                    ->route('admin.repayments.show', $repayment)
                    ->with('error', $paymentResult['message'] ?? 'Repayment submission failed at gateway stage.');
            }

            $gatewayMetadata = [];
            if (isset($paymentResult['metadata']) && is_array($paymentResult['metadata'])) {
                $gatewayMetadata = $paymentResult['metadata'];
            }

            $externalReference = $paymentResult['reference'] ?? $repayment->external_reference;
            $externalTransactionId = $paymentResult['transaction_id'] ?? $repayment->external_transaction_id;
            $statusMessage = $paymentResult['message'] ?? 'Payment prompt sent and awaiting provider confirmation.';
            $updatedMetadata = array_merge($metadata, $gatewayMetadata, [
                'gateway_reference' => $paymentResult['reference'] ?? null,
                'gateway_transaction_id' => $paymentResult['transaction_id'] ?? null,
                'gateway_initiated_by_admin_id' => auth('admin')->id(),
                'gateway_initiated_at' => now()->toIso8601String(),
            ]);

            $repayment->update([
                'external_reference' => $externalReference,
                'external_transaction_id' => $externalTransactionId,
                'status' => 'processing',
                'status_message' => $statusMessage,
                'metadata' => $updatedMetadata,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('status', 'Repayment submitted to provider and is now processing.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Admin repayment submission failed', [
                'customer_id' => $customer->id,
                'admin_id' => auth('admin')->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Failed to submit repayment: '.$e->getMessage());
        }
    }

    public function approve(Request $request, Repayment $repayment): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('repayments.approve') || $admin?->can('repayments.process'), 403);

        if ($repayment->status !== 'pending') {
            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'Only pending repayments can be approved.');
        }

        if ($repayment->loanRepayments()->exists()) {
            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'This repayment has already been applied to loans.');
        }

        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'manual_source' => 'required|in:bank,wallet,cash',
            'bank_id' => 'nullable|required_if:manual_source,bank|exists:banks,id',
            'wallet_id' => 'nullable|required_if:manual_source,wallet|exists:wallets,id',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
            'external_reference' => 'nullable|string|max:255',
            'external_transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $channel = Channel::query()
            ->where('id', $validated['channel_id'])
            ->where('is_active', true)
            ->where('can_repay', true)
            ->first();

        if (! $channel) {
            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->withInput()
                ->withErrors([
                    'channel_id' => 'The selected repayment channel is not active for repayments.',
                ]);
        }

        $customer = $repayment->customer;
        if (! $customer) {
            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'Repayment customer record is missing.');
        }

        $metadata = $repayment->metadata ?? [];
        $repaymentType = (string) ($metadata['repayment_type'] ?? 'full');
        $loanId = isset($metadata['loan_id']) ? (int) $metadata['loan_id'] : null;

        $manualSource = $validated['manual_source'];
        [$receivedViaType, $receivedViaId] = $this->resolveReceivedVia(
            $manualSource,
            isset($validated['bank_id']) ? (int) $validated['bank_id'] : null,
            isset($validated['wallet_id']) ? (int) $validated['wallet_id'] : null,
            isset($validated['cash_register_id']) ? (int) $validated['cash_register_id'] : null
        );

        try {
            DB::beginTransaction();

            $updatedMetadata = array_merge($metadata, [
                'manual_source' => $manualSource,
                'approved_channel_id' => (int) $channel->id,
                'approved_by_admin_id' => auth('admin')->id(),
                'approved_at' => now()->toIso8601String(),
                'approval_notes' => $validated['notes'] ?? null,
            ]);

            $repayment->update([
                'channel_id' => (int) $channel->id,
                'status' => 'completed',
                'processed_at' => now(),
                'status_message' => $validated['notes']
                    ?? 'Repayment approved and processed manually.',
                'external_reference' => $validated['external_reference'] ?? $repayment->external_reference,
                'external_transaction_id' => $validated['external_transaction_id'] ?? $repayment->external_transaction_id,
                'received_via_type' => $receivedViaType,
                'received_via_id' => $receivedViaId,
                'metadata' => $updatedMetadata,
            ]);

            if ($receivedViaType === 'bank' && $receivedViaId) {
                $bank = Bank::find($receivedViaId);
                $bank?->updateBalance((float) $repayment->total_amount, 'credit');
            }

            if ($receivedViaType === 'wallet' && $receivedViaId) {
                $wallet = Wallet::find($receivedViaId);
                $wallet?->updateBalance((float) $repayment->total_amount, 'credit');
            }

            if ($receivedViaType === 'cash' && $receivedViaId) {
                $cashRegister = CashRegister::find($receivedViaId);
                $cashRegister?->updateBalance((float) $repayment->total_amount, 'credit');
            }

            $this->repaymentProcessingService->applyRepaymentToLoans(
                $repayment,
                $customer,
                $repaymentType,
                $loanId,
                (float) $repayment->total_amount,
                'Approved repayment'
            );

            DB::commit();

            try {
                $this->customerNotificationService->sendRepaymentCompleted(
                    $repayment->fresh(['customer', 'channel', 'loanRepayments.loan']),
                    'manual_approval'
                );
            } catch (\Throwable $notificationError) {
                Log::error('Failed to send repayment completion notifications', [
                    'repayment_id' => $repayment->id,
                    'error' => $notificationError->getMessage(),
                ]);
            }

            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('status', 'Repayment approved and applied successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Repayment approval failed', [
                'repayment_id' => $repayment->id,
                'admin_id' => auth('admin')->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'Failed to approve repayment: '.$e->getMessage());
        }
    }

    public function reject(Request $request, Repayment $repayment): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('repayments.reject') || $admin?->can('repayments.process'), 403);

        if ($repayment->status !== 'pending') {
            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'Only pending repayments can be rejected.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $metadata = $repayment->metadata ?? [];
        $metadata['rejected_by_admin_id'] = auth('admin')->id();
        $metadata['rejected_at'] = now()->toIso8601String();
        $metadata['rejection_reason'] = $validated['reason'];

        $repayment->update([
            'status' => 'failed',
            'status_message' => $validated['reason'],
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('admin.repayments.show', $repayment)
            ->with('status', 'Repayment has been rejected.');
    }

    public function updateProcessingStatus(Request $request, Repayment $repayment): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('repayments.process') || $admin?->can('repayments.approve'), 403);

        if ($repayment->status !== 'processing') {
            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'Only repayments in processing status can be updated.');
        }

        $validated = $request->validate([
            'provider_status' => 'required|in:success,failed',
            'provider_message' => 'nullable|string|max:1000',
            'external_reference' => 'nullable|string|max:255',
            'external_transaction_id' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            if ($validated['provider_status'] === 'success') {
                $this->repaymentProcessingService->finalizeIntegratedRepayment(
                    $repayment,
                    [
                        'reference' => $validated['external_reference'] ?? null,
                        'transaction_id' => $validated['external_transaction_id'] ?? null,
                        'message' => $validated['provider_message'] ?? 'Payment confirmed by provider and applied to customer loans.',
                        'metadata' => [
                            'provider_status' => 'success',
                            'provider_confirmed_by_admin_id' => auth('admin')->id(),
                        ],
                    ],
                    'Provider confirmed repayment'
                );

                DB::commit();

                try {
                    $this->customerNotificationService->sendRepaymentCompleted(
                        $repayment->fresh(['customer', 'channel', 'loanRepayments.loan']),
                        'provider_confirmation'
                    );
                } catch (\Throwable $notificationError) {
                    Log::error('Failed to send provider-confirmed repayment notifications', [
                        'repayment_id' => $repayment->id,
                        'error' => $notificationError->getMessage(),
                    ]);
                }

                return redirect()
                    ->route('admin.repayments.show', $repayment)
                    ->with('status', 'Repayment marked as completed and applied successfully.');
            }

            $metadata = array_merge($repayment->metadata ?? [], [
                'provider_status' => 'failed',
                'provider_failed_by_admin_id' => auth('admin')->id(),
                'provider_failed_at' => now()->toIso8601String(),
            ]);

            $repayment->update([
                'status' => 'failed',
                'status_message' => $validated['provider_message'] ?? 'Provider reported payment failure.',
                'external_reference' => $validated['external_reference'] ?? $repayment->external_reference,
                'external_transaction_id' => $validated['external_transaction_id'] ?? $repayment->external_transaction_id,
                'metadata' => $metadata,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('status', 'Repayment marked as failed based on provider response.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update processing repayment status', [
                'repayment_id' => $repayment->id,
                'admin_id' => auth('admin')->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.repayments.show', $repayment)
                ->with('error', 'Failed to update repayment status: '.$e->getMessage());
        }
    }

    private function calculateRepaymentAmount(
        Customer $customer,
        string $type,
        ?Loan $selectedLoan = null,
        ?float $amount = null
    ): float {
        return match ($type) {
            'partial' => (float) ($amount ?? 0),
            'overdue' => (float) $customer->getTotalOverdueAmount(),
            'full' => (float) $customer->getTotalOutstandingBalance(),
            default => 0.0,
        };
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveReceivedVia(
        ?string $manualSource,
        ?int $bankId,
        ?int $walletId,
        ?int $cashRegisterId = null
    ): array {
        if ($manualSource === 'bank') {
            return ['bank', $bankId];
        }

        if ($manualSource === 'wallet') {
            return ['wallet', $walletId];
        }

        if ($manualSource === 'cash') {
            return ['cash', $this->cashRegisterService->resolveRegisterId($cashRegisterId)];
        }

        return [null, null];
    }
}
