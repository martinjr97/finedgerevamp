<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    /**
     * Handle customer login via API
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Normalize phone number - remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $request->phone);

        // Find customer by phone number (normalize stored phone numbers for comparison)
        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })->first();

        if (! $customer || ! Hash::check($request->pin, $customer->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Only approved customers can sign in
        if ($customer->approval_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has not been approved. Please contact support.',
            ], 403);
        }

        // Check if customer is active
        if ($customer->status !== 'active') {
            $isBlocked = in_array($customer->status, ['suspended', 'blocked'], true);

            return response()->json([
                'success' => false,
                'message' => $isBlocked
                    ? 'Your account is blocked. Please contact support.'
                    : 'Your account is not active. Please contact support.',
            ], 403);
        }

        // Revoke existing tokens for this device if device_name is provided
        if ($request->device_name) {
            $customer->tokens()->where('name', $request->device_name)->delete();
        }

        // Create new token
        $deviceName = $request->device_name ?? 'mobile-app-'.now()->timestamp;
        $token = $customer->createToken($deviceName);

        // Update last login
        $customer->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        // Load relationships for dashboard data
        $customer->load(['loanProduct', 'customerGroup', 'loans', 'company']);

        // Get dashboard data
        $activeLoans = $customer->activeLoans();
        $totalOutstandingBalance = $customer->getTotalOutstandingBalance();
        $availableLoanAmount = $customer->getAvailableLoanAmount();
        $nextPaymentDate = $customer->getNextPaymentDate();
        $canTakeAnotherLoan = $customer->canTakeAnotherLoan();
        $loanEligibilityBlockingMessage = $canTakeAnotherLoan ? null : $customer->loanEligibilityBlockingMessage();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'customer' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'dashboard' => [
                    'active_loans_count' => $activeLoans->count(),
                    'total_outstanding_balance' => $totalOutstandingBalance,
                    'available_loan_amount' => $availableLoanAmount,
                    'maximum_loan_take' => $customer->maximum_loan_take ?? 0,
                    'next_payment_date' => $nextPaymentDate?->format('Y-m-d'),
                    'next_payment_date_human' => $nextPaymentDate?->diffForHumans(),
                    'can_take_another_loan' => $canTakeAnotherLoan,
                    'loan_eligibility_blocking_message' => $loanEligibilityBlockingMessage,
                ],
            ],
        ]);
    }

    /**
     * Get authenticated customer details
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
        ]);
    }

    /**
     * Handle customer logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Refresh token (create new token and revoke old one)
     */
    public function refresh(Request $request): JsonResponse
    {
        $customer = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $deviceName = $request->input('device_name', 'mobile-app-'.now()->timestamp);
        $token = $customer->createToken($deviceName);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
