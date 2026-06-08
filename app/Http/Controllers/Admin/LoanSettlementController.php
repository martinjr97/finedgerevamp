<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ZambianPhoneRules;
use App\Models\Loan;
use App\Services\LoanSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanSettlementController extends Controller
{
    public function __construct(
        private readonly LoanSettlementService $settlementService,
    ) {}

    public function quote(Request $request, Loan $loan): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $validated = $request->validate([
            'settlement_date' => 'nullable|date',
        ]);

        $quote = $this->settlementService->quoteSettlement(
            $loan,
            $validated['settlement_date'] ?? null
        );

        return response()->json($quote);
    }

    public function apply(Request $request, Loan $loan): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.disburse'), 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'settlement_date' => 'nullable|date',
            'channel_id' => 'nullable|exists:channels,id',
            'phone_number' => ZambianPhoneRules::nullable(),
            'notes' => 'nullable|string|max:1000',
            'allow_partial' => 'nullable|boolean',
        ]);

        $loanRepayment = $this->settlementService->applySettlement($loan, [
            'amount' => $validated['amount'],
            'settlement_date' => $validated['settlement_date'] ?? null,
            'channel_id' => $validated['channel_id'] ?? $loan->channel_id,
            'phone_number' => $validated['phone_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'allow_partial' => (bool) ($validated['allow_partial'] ?? false),
        ]);

        $loan->refresh();

        return response()->json([
            'message' => 'Loan settled successfully.',
            'loan' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'settlement_amount' => $loan->settlement_amount,
                'settlement_date' => $loan->settlement_date?->toDateString(),
                'rebate_amount' => $loan->rebate_amount,
                'loan_settled_date' => $loan->loan_settled_date?->toDateString(),
                'outstanding_balance' => $loan->outstanding_balance,
            ],
            'loan_repayment_id' => $loanRepayment->id,
        ]);
    }
}
