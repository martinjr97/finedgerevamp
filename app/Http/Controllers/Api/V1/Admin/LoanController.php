<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * List loans
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('loans.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view loans.',
            ], 403);
        }

        $query = Loan::with(['customer', 'loanProduct', 'customerGroup', 'channel', 'approver']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('accrual_type') && $request->accrual_type) {
            $query->where('accrual_type', $request->accrual_type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('loan_start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('loan_start_date', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min($request->get('per_page', 20), 100);
        $loans = $query->latest('loan_start_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\LoanResource::collection($loans),
            'meta' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ],
        ]);
    }

    /**
     * Show a specific loan
     */
    public function show(Loan $loan): JsonResponse
    {
        $admin = request()->user();
        
        if (!$admin->can('loans.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view loans.',
            ], 403);
        }

        $loan->load(['customer', 'loanProduct', 'customerGroup', 'channel', 'approver', 'loanRate']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\LoanResource($loan),
        ]);
    }

    /**
     * Approve a loan
     */
    public function approve(Request $request, Loan $loan): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('loans.approve')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to approve loans.',
            ], 403);
        }

        if ($loan->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending loans can be approved.',
            ], 422);
        }

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $loan->status = 'approved';
        $loan->approved_by = $admin->id;
        $loan->approved_at = now();
        $loan->approval_notes = $validated['approval_notes'] ?? null;
        $loan->save();

        $loan->load(['customer', 'loanProduct', 'customerGroup', 'channel', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Loan approved successfully.',
            'data' => new \App\Http\Resources\Api\V1\LoanResource($loan),
        ]);
    }

    /**
     * Reject a loan
     */
    public function reject(Request $request, Loan $loan): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('loans.reject')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reject loans.',
            ], 403);
        }

        if ($loan->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending loans can be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'approval_notes' => ['required', 'string', 'max:1000'],
        ]);

        $loan->status = 'rejected';
        $loan->approved_by = $admin->id;
        $loan->approved_at = now();
        $loan->approval_notes = $validated['approval_notes'];
        $loan->save();

        $loan->load(['customer', 'loanProduct', 'customerGroup', 'channel', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Loan rejected successfully.',
            'data' => new \App\Http\Resources\Api\V1\LoanResource($loan),
        ]);
    }
}

