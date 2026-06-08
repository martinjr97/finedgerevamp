<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    /**
     * List loan products (view only)
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin->can('loan-products.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view loan products.',
            ], 403);
        }

        $query = LoanProduct::with(['company']);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->get('per_page', 20), 100);
        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\LoanProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Show a specific loan product
     */
    public function show(LoanProduct $loanProduct): JsonResponse
    {
        $admin = request()->user();
        
        if (!$admin->can('loan-products.view')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view loan products.',
            ], 403);
        }

        $loanProduct->load(['company']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\LoanProductResource($loanProduct),
        ]);
    }
}

