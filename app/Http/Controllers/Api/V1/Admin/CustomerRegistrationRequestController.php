<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRegistrationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerRegistrationRequestController extends Controller
{
    /**
     * List customer registration requests
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customer-requests.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customer registration requests.',
            ], 403);
        }

        $query = CustomerRegistrationRequest::with(['product', 'group'])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        if ($productId = $request->input('loan_product_id')) {
            $query->where('loan_product_id', (int) $productId);
        }

        if ($groupId = $request->input('customer_group_id')) {
            $query->where('customer_group_id', (int) $groupId);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\CustomerRegistrationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Show a specific customer registration request
     */
    public function show(CustomerRegistrationRequest $registrationRequest): JsonResponse
    {
        $admin = request()->user();
        
        if (!$admin->can('customer-requests.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view customer registration requests.',
            ], 403);
        }

        $registrationRequest->load(['product', 'group']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\CustomerRegistrationRequestResource($registrationRequest),
        ]);
    }

    /**
     * Approve a customer registration request
     */
    public function approve(Request $request, CustomerRegistrationRequest $registrationRequest): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('customer-requests.approve')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to approve customer registration requests.',
            ], 403);
        }

        if ($registrationRequest->created_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been converted into a customer and cannot be modified.',
            ], 422);
        }

        if ($registrationRequest->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'This request is already approved.',
            ], 422);
        }

        $registrationRequest->status = 'approved';
        $registrationRequest->save();

        $registrationRequest->load(['product', 'group']);

        return response()->json([
            'success' => true,
            'message' => 'Customer registration request approved successfully.',
            'data' => new \App\Http\Resources\Api\V1\CustomerRegistrationRequestResource($registrationRequest),
        ]);
    }
}

