<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get customer profile
     */
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();
        $customer->load(['company', 'loanProduct', 'customerGroup']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
        ]);
    }
}

