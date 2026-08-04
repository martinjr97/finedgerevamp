<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Loan;
use App\Models\Repayment;
use App\Services\Repayments\AdminRepaymentGatewayCollectionService;
use App\Services\Repayments\Enums\RepaymentGatewayCollectionStatus;
use App\Support\ZambianPhoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RepaymentController extends Controller
{
    public function __construct(
        private readonly AdminRepaymentGatewayCollectionService $gatewayCollectionService,
    ) {}

    /**
     * Show repayment type selection page (Step 1)
     */
    public function selectType(): View|RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->load(['loans' => function ($query) {
            $query->whereIn('status', ['approved', 'active']);
        }]);

        $activeLoans = $customer->activeLoans();

        if ($activeLoans->isEmpty()) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'You have no active loans to repay.');
        }

        $totalOutstandingBalance = $customer->getTotalOutstandingBalance();
        $totalOverdueAmount = $customer->getTotalOverdueAmount();
        $hasOverdue = $customer->hasOverdueLoans();

        return view('customer.repayments.select-type', [
            'activeLoans' => $activeLoans,
            'totalOutstandingBalance' => $totalOutstandingBalance,
            'totalOverdueAmount' => $totalOverdueAmount,
            'hasOverdue' => $hasOverdue,
        ]);
    }

    /**
     * Store repayment type selection and redirect to channel selection
     */
    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'repayment_type' => 'required|in:partial,overdue,full',
            'loan_id' => 'nullable|exists:loans,id',
            'amount' => 'required_if:repayment_type,partial|nullable|numeric|min:0.01',
        ]);

        $customer = auth('customer')->user();

        // Verify loan belongs to customer
        if (isset($validated['loan_id'])) {
            Loan::where('id', $validated['loan_id'])
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['approved', 'active'])
                ->firstOrFail();
        }

        // Store in session
        session([
            'repayment.type' => $validated['repayment_type'],
            'repayment.loan_id' => $validated['loan_id'] ?? null,
            'repayment.amount' => $validated['amount'] ?? null,
        ]);

        return redirect()->route('customer.repayments.select-channel');
    }

    /**
     * Show channel selection page (Step 2)
     */
    public function selectChannel(): View|RedirectResponse
    {
        if (! session('repayment.type')) {
            return redirect()->route('customer.repayments.select-type')
                ->with('error', 'Please select a repayment type first.');
        }

        $repaymentType = session('repayment.type');
        $repaymentAmount = $this->calculateRepaymentAmount($repaymentType, session('repayment.loan_id'), session('repayment.amount'));

        if ($repaymentAmount <= 0) {
            return redirect()->route('customer.repayments.select-type')
                ->with('error', 'Invalid repayment amount. Please try again.');
        }

        $channels = Channel::where('is_active', true)
            ->where('can_repay', true)
            ->where('type', '!=', Channel::TYPE_CASH)
            ->orderBy('name')
            ->get();

        if ($channels->isEmpty()) {
            return redirect()->route('customer.repayments.select-type')
                ->with('error', 'No repayment channels are currently available. Please contact support.');
        }

        return view('customer.repayments.select-channel', [
            'channels' => $channels,
            'repaymentType' => $repaymentType,
            'repaymentAmount' => $repaymentAmount,
            'selectedLoan' => session('repayment.loan_id') ? Loan::find(session('repayment.loan_id')) : null,
        ]);
    }

    /**
     * Store channel selection and redirect to confirmation
     */
    public function storeChannel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'phone_number' => ZambianPhoneRules::nullable(),
        ]);

        Channel::where('id', $validated['channel_id'])
            ->where('is_active', true)
            ->where('can_repay', true)
            ->where('type', '!=', Channel::TYPE_CASH)
            ->firstOrFail();

        session([
            'repayment.channel_id' => $validated['channel_id'],
            'repayment.phone_number' => $validated['phone_number'] ?? auth('customer')->user()->phone,
        ]);

        return redirect()->route('customer.repayments.confirm');
    }

    /**
     * Show repayment confirmation page (Step 3)
     */
    public function confirm(): View|RedirectResponse
    {
        if (! session('repayment.type') || ! session('repayment.channel_id')) {
            return redirect()->route('customer.repayments.select-type')
                ->with('error', 'Please complete the repayment steps first.');
        }

        $repaymentType = session('repayment.type');
        $channel = Channel::findOrFail(session('repayment.channel_id'));
        $repaymentAmount = $this->calculateRepaymentAmount(
            $repaymentType,
            session('repayment.loan_id'),
            session('repayment.amount')
        );
        $selectedLoan = session('repayment.loan_id') ? Loan::find(session('repayment.loan_id')) : null;

        return view('customer.repayments.confirm', [
            'repaymentType' => $repaymentType,
            'channel' => $channel,
            'repaymentAmount' => $repaymentAmount,
            'selectedLoan' => $selectedLoan,
            'phoneNumber' => session('repayment.phone_number'),
        ]);
    }

    /**
     * Process repayment (Step 4 - Final)
     */
    public function process(Request $request): RedirectResponse
    {
        if (! session('repayment.type') || ! session('repayment.channel_id')) {
            return redirect()->route('customer.repayments.select-type')
                ->with('error', 'Please complete the repayment steps first.');
        }

        $customer = auth('customer')->user();
        $repaymentType = session('repayment.type');
        $loanId = session('repayment.loan_id');
        $channel = Channel::findOrFail(session('repayment.channel_id'));
        $repaymentAmount = $this->calculateRepaymentAmount(
            $repaymentType,
            $loanId,
            session('repayment.amount')
        );

        if ($repaymentAmount <= 0) {
            return redirect()->route('customer.repayments.select-type')
                ->with('error', 'Invalid repayment amount. Please try again.');
        }

        try {
            DB::beginTransaction();

            $phoneNumber = session('repayment.phone_number');
            $collectionPreview = $this->gatewayCollectionService->previewForChannel(
                $channel,
                $repaymentAmount,
                $phoneNumber,
            );

            $metadata = [
                'repayment_type' => $repaymentType,
                'loan_id' => $loanId,
                'submission_mode' => $collectionPreview->ready ? 'gateway_collection' : 'customer_portal',
                'submitted_from' => 'customer_portal',
                'submitted_at' => now()->toIso8601String(),
                'gateway_collection_preview' => $collectionPreview->toArray(),
            ];

            if ($collectionPreview->ready) {
                $repayment = Repayment::create([
                    'customer_id' => $customer->id,
                    'channel_id' => $channel->id,
                    'repayment_number' => Repayment::generateRepaymentNumber(),
                    'total_amount' => $repaymentAmount,
                    'phone_number' => $phoneNumber,
                    'status' => 'processing',
                    'status_message' => 'Repayment submitted for gateway collection.',
                    'metadata' => $metadata,
                ]);

                $collectionResult = $this->gatewayCollectionService->initiateForRepayment(
                    $repayment,
                    $channel,
                    $phoneNumber,
                );

                if ($collectionResult->status === RepaymentGatewayCollectionStatus::Initiated) {
                    $repayment->update([
                        'external_reference' => $collectionResult->reference ?? $repayment->external_reference,
                        'external_transaction_id' => $collectionResult->transactionId ?? $repayment->external_transaction_id,
                        'status' => 'processing',
                        'status_message' => $collectionResult->message,
                        'metadata' => array_merge($metadata, $collectionResult->gatewayMetadata, [
                            'gateway_reference' => $collectionResult->reference,
                            'gateway_transaction_id' => $collectionResult->transactionId,
                            'gateway_initiated_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    DB::commit();
                    session()->forget(['repayment.type', 'repayment.loan_id', 'repayment.amount', 'repayment.channel_id', 'repayment.phone_number']);

                    return redirect()->route('customer.repayments.success')
                        ->with('repayment_success', [
                            'state' => 'provider_prompt',
                            'title' => 'Payment Prompt Sent',
                            'subtitle' => 'Approve the payment prompt on your phone to complete this repayment.',
                            'detail' => 'After provider confirmation, we will update your repayment status and notify you by SMS and email.',
                            'repayment_number' => $repayment->repayment_number,
                        ]);
                }

                if ($collectionResult->status === RepaymentGatewayCollectionStatus::FallbackManual) {
                    $repayment->update([
                        'status' => 'pending',
                        'status_message' => 'Repayment awaiting manual processing after gateway collection could not be initiated.',
                        'metadata' => array_merge($metadata, [
                            'submission_mode' => 'customer_portal',
                            'gateway_collection_fallback' => true,
                            'gateway_collection_failure' => $collectionResult->message,
                            'gateway_fallback_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    DB::commit();
                    session()->forget(['repayment.type', 'repayment.loan_id', 'repayment.amount', 'repayment.channel_id', 'repayment.phone_number']);

                    return redirect()->route('customer.repayments.success')
                        ->with('repayment_success', [
                            'state' => 'manual_pending',
                            'title' => 'Repayment Submitted',
                            'subtitle' => 'Your repayment has been submitted for processing.',
                            'detail' => $collectionResult->message,
                            'repayment_number' => $repayment->repayment_number,
                        ]);
                }

                $repayment->update([
                    'status' => 'failed',
                    'status_message' => $collectionResult->message,
                    'metadata' => array_merge($metadata, [
                        'gateway_response' => ['message' => $collectionResult->message],
                        'failed_at' => now()->toIso8601String(),
                    ]),
                ]);

                DB::commit();

                return redirect()->route('customer.repayments.confirm')
                    ->with('error', $collectionResult->message);
            }

            $repayment = Repayment::create([
                'customer_id' => $customer->id,
                'channel_id' => $channel->id,
                'repayment_number' => Repayment::generateRepaymentNumber(),
                'total_amount' => $repaymentAmount,
                'phone_number' => $phoneNumber,
                'status' => 'pending',
                'status_message' => 'Repayment submitted and awaiting manual approval.',
                'metadata' => $metadata,
            ]);

            DB::commit();
            session()->forget(['repayment.type', 'repayment.loan_id', 'repayment.amount', 'repayment.channel_id', 'repayment.phone_number']);

            return redirect()->route('customer.repayments.success')
                ->with('repayment_success', [
                    'state' => 'manual_pending',
                    'title' => 'Repayment Submitted',
                    'subtitle' => 'Your repayment has been submitted for processing.',
                    'detail' => 'Our team will verify and approve the repayment before balances are updated.',
                    'repayment_number' => $repayment->repayment_number,
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Repayment processing error', [
                'customer_id' => $customer->id,
                'amount' => $repaymentAmount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('customer.repayments.confirm')
                ->with('error', 'An error occurred while processing your repayment. Please try again or contact support.');
        }
    }

    /**
     * Show repayment success page
     */
    public function success(Request $request): View
    {
        $context = $request->session()->get('repayment_success', [
            'state' => 'submitted',
            'title' => 'Repayment Submitted',
            'subtitle' => 'Your repayment request has been received.',
            'detail' => 'You will receive updates in your notifications, email, and SMS once processing is completed.',
            'repayment_number' => null,
        ]);

        return view('customer.repayments.success', [
            'context' => $context,
        ]);
    }

    /**
     * Calculate repayment amount based on type
     */
    private function calculateRepaymentAmount(string $type, ?int $loanId = null, ?float $amount = null): float
    {
        $customer = auth('customer')->user();

        switch ($type) {
            case 'partial':
                $maxAmount = $loanId
                    ? (float) (Loan::query()
                        ->where('id', $loanId)
                        ->where('customer_id', $customer->id)
                        ->whereIn('status', ['approved', 'active'])
                        ->value('outstanding_balance') ?? 0)
                    : (float) $customer->getTotalOutstandingBalance();

                return min($amount ?? 0, $maxAmount);

            case 'overdue':
                return $customer->getTotalOverdueAmount();

            case 'full':
                return $customer->getTotalOutstandingBalance();

            default:
                return 0;
        }
    }
}
