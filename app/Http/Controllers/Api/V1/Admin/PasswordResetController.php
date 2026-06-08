<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Send password reset OTP to admin's phone
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'We could not find an account with that email address.',
            ], 404);
        }

        if (!$admin->phone) {
            return response()->json([
                'success' => false,
                'message' => 'Your account does not have a phone number registered. Please contact support.',
            ], 422);
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in cache for 10 minutes
        $cacheKey = 'admin_password_reset_otp_' . $admin->id;
        Cache::put($cacheKey, [
            'otp' => $otp,
            'email' => $admin->email,
            'expires_at' => now()->addMinutes(10),
        ], now()->addMinutes(10));

        // TODO: Send OTP via SMS service
        // For now, log it (in production, integrate your SMS service)
        $otpMessage = "Your password reset OTP is: {$otp}. Valid for 10 minutes.";
        
        \Log::info('Password Reset OTP Sent to Phone', [
            'admin_id' => $admin->id,
            'phone' => $admin->phone,
            'otp' => $otp, // Remove this in production
        ]);

        // Log to communications
        try {
            \App\Support\CommunicationLogger::log(
                subject: 'Password Reset OTP',
                message: $otpMessage,
                type: 'sms',
                isSensitive: true,
                recipient: $admin,
                createdBy: null, // System generated
                metadata: ['notification_type' => 'password_reset_otp']
            );
        } catch (\Exception $e) {
            \Log::error('Failed to log OTP communication', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP has been sent to your registered phone number.',
            'data' => [
                'email' => $admin->email,
                'phone_masked' => substr($admin->phone, 0, 3) . '****' . substr($admin->phone, -2),
                'expires_in_minutes' => 10,
            ],
        ]);
    }

    /**
     * Verify OTP and get reset token
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address.',
            ], 404);
        }

        $cacheKey = 'admin_password_reset_otp_' . $admin->id;
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || $cachedData['otp'] !== $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please request a new one.',
            ], 422);
        }

        // OTP verified - create a reset token
        $token = Str::random(64);
        
        // Store reset token in cache for 1 hour
        $resetTokenKey = 'admin_password_reset_token_' . $admin->id;
        Cache::put($resetTokenKey, [
            'token' => $token,
            'email' => $admin->email,
            'expires_at' => now()->addHour(),
        ], now()->addHour());

        // Clear OTP from cache
        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully. Use the reset token to reset your password.',
            'data' => [
                'reset_token' => $token,
                'email' => $admin->email,
                'expires_in_minutes' => 60,
            ],
        ]);
    }

    /**
     * Reset password using reset token
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address.',
            ], 404);
        }

        $resetTokenKey = 'admin_password_reset_token_' . $admin->id;
        $cachedData = Cache::get($resetTokenKey);

        if (!$cachedData || $cachedData['token'] !== $validated['token']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token. Please request a new password reset.',
            ], 422);
        }

        // Update password
        $admin->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        // Clear reset token from cache (one-time use)
        Cache::forget($resetTokenKey);

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully. You can now login with your new password.',
        ]);
    }
}

