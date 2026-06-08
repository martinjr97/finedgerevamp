<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Send PIN reset OTP to customer's phone
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $validated['phone']);

        // Find customer by phone and national ID
        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $validated['national_id'])
        ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'We could not find an account matching the provided phone number and National ID.',
            ], 404);
        }

        if (!$customer->phone) {
            return response()->json([
                'success' => false,
                'message' => 'Your account does not have a phone number registered. Please contact support.',
            ], 422);
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in cache for 10 minutes
        $cacheKey = 'customer_password_reset_otp_' . $customer->id;
        Cache::put($cacheKey, [
            'otp' => $otp,
            'phone' => $phone,
            'national_id' => $validated['national_id'],
            'expires_at' => now()->addMinutes(10),
        ], now()->addMinutes(10));

        // TODO: Send OTP via SMS service
        // For now, log it (in production, integrate your SMS service)
        $otpMessage = "Your password reset OTP is: {$otp}. Valid for 10 minutes.";
        
        \Log::info('Customer Password Reset OTP Sent to Phone', [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'national_id' => $customer->national_id,
            'otp' => $otp, // Remove this in production
        ]);

        // Log to communications
        try {
            \App\Support\CommunicationLogger::log(
                subject: 'Password Reset OTP - ' . config('app.name'),
                message: $otpMessage,
                type: 'sms',
                isSensitive: true,
                recipient: $customer,
                createdBy: null, // System generated
                metadata: ['notification_type' => 'password_reset_otp']
            );
        } catch (\Exception $e) {
            \Log::error('Failed to log OTP communication', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'An OTP has been sent to your registered phone number.',
            'data' => [
                'phone_masked' => substr($customer->phone, 0, 3) . '****' . substr($customer->phone, -2),
                'expires_in_minutes' => 10,
            ],
        ]);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $validated['phone']);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $validated['national_id'])
        ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number or National ID.',
            ], 404);
        }

        $cacheKey = 'customer_password_reset_otp_' . $customer->id;
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || $cachedData['otp'] !== $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please request a new one.',
            ], 422);
        }

        // Check if customer has security question set
        if (!$customer->security_question_id || !$customer->security_answer) {
            return response()->json([
                'success' => false,
                'message' => 'Security question not set. Please contact support.',
            ], 422);
        }

        // OTP verified - store verification in cache
        $verifyKey = 'customer_password_reset_verified_' . $customer->id;
        Cache::put($verifyKey, [
            'phone' => $phone,
            'national_id' => $validated['national_id'],
            'expires_at' => now()->addMinutes(15),
        ], now()->addMinutes(15));

        // Clear OTP from cache
        Cache::forget($cacheKey);

        // Load security question
        $customer->load('securityQuestion');

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully. Please answer your security question.',
            'data' => [
                'security_question' => [
                    'id' => $customer->securityQuestion->id,
                    'question' => $customer->securityQuestion->question,
                ],
            ],
        ]);
    }

    /**
     * Verify security question and get reset token
     */
    public function verifySecurityQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
            'security_answer' => ['required', 'string'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $validated['phone']);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $validated['national_id'])
        ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number or National ID.',
            ], 404);
        }

        // Verify OTP was verified
        $verifyKey = 'customer_password_reset_verified_' . $customer->id;
        $verified = Cache::get($verifyKey);

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete OTP verification first.',
            ], 422);
        }

        // Verify security answer (case-insensitive)
        if (strtolower(trim($customer->security_answer)) !== strtolower(trim($validated['security_answer']))) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect security answer. Please try again.',
            ], 422);
        }

        // Security question verified - create reset token
        $token = Str::random(64);
        
        // Store reset token in cache for 1 hour
        $resetTokenKey = 'customer_password_reset_token_' . $customer->id;
        Cache::put($resetTokenKey, [
            'token' => $token,
            'phone' => $phone,
            'national_id' => $validated['national_id'],
            'expires_at' => now()->addHour(),
        ], now()->addHour());

        // Clear verification from cache
        Cache::forget($verifyKey);

        return response()->json([
            'success' => true,
            'message' => 'Security question verified successfully. Use the reset token to reset your PIN.',
            'data' => [
                'reset_token' => $token,
                'phone' => $phone,
                'national_id' => $validated['national_id'],
                'expires_in_minutes' => 60,
            ],
        ]);
    }

    /**
     * Reset PIN using reset token
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'confirmed'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $validated['phone']);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $validated['national_id'])
        ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number or National ID.',
            ], 404);
        }

        $resetTokenKey = 'customer_password_reset_token_' . $customer->id;
        $cachedData = Cache::get($resetTokenKey);

        if (!$cachedData || $cachedData['token'] !== $validated['token']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token. Please request a new password reset.',
            ], 422);
        }

        // Update password (PIN)
        $customer->forceFill([
            'password' => Hash::make($validated['pin']),
            'must_change_pin' => false,
        ])->save();

        // Clear reset token from cache (one-time use)
        Cache::forget($resetTokenKey);

        return response()->json([
            'success' => true,
            'message' => 'Your PIN has been reset successfully. You can now login with your new PIN.',
        ]);
    }
}

