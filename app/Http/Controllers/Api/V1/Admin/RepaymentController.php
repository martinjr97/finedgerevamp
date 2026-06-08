<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Repayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
    /**
     * List repayments (view only)
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('repayments.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view repayments.',
            ], 403);
        }

        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan.loanProduct']);

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

        $perPage = min($request->get('per_page', 20), 100);
        $repayments = $query->latest('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\RepaymentResource::collection($repayments),
            'meta' => [
                'current_page' => $repayments->currentPage(),
                'last_page' => $repayments->lastPage(),
                'per_page' => $repayments->perPage(),
                'total' => $repayments->total(),
            ],
        ]);
    }

    /**
     * Show a specific repayment
     */
    public function show(Repayment $repayment): JsonResponse
    {
        $admin = request()->user();
        
        if (!$admin->can('repayments.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view repayments.',
            ], 403);
        }

        $repayment->load(['customer', 'channel', 'loanRepayments.loan.loanProduct']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\RepaymentResource($repayment),
        ]);
    }
}

