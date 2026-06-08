<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Display the password reset request form.
     */
    public function showForgotPasswordForm(): View
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * Handle password reset request - send OTP to phone only.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'We could not find an account with that email address.');
        }

        if (!$admin->phone) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account does not have a phone number registered. Please contact support.');
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

        // Send OTP via SMS to phone number
        // TODO: Integrate your SMS service here
        // For now, we'll log it. Replace this with your SMS sending code
        $otpMessage = "Your password reset OTP is: {$otp}. Valid for 10 minutes.";
        
        \Log::info('Password Reset OTP Sent to Phone', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'otp' => $otp,
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

        // Example SMS integration (uncomment and configure when ready):
        // $this->sendSms($admin->phone, $otpMessage);

        return redirect()->route('admin.password.verify-otp')
            ->with('status', 'An OTP has been sent to your registered phone number. Please check your phone and enter the code.')
            ->with('email', $admin->email);
    }

    /**
     * Display the OTP verification form.
     */
    public function showVerifyOtpForm(Request $request): View
    {
        $email = $request->session()->get('email') ?? old('email');
        
        if (!$email) {
            return redirect()->route('admin.password.forgot')
                ->with('error', 'Please request a password reset first.');
        }

        return view('admin.auth.verify-otp', ['email' => $email]);
    }

    /**
     * Verify OTP and proceed to password reset.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return redirect()->route('admin.password.forgot')
                ->with('error', 'Invalid email address.');
        }

        $cacheKey = 'admin_password_reset_otp_' . $admin->id;
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || $cachedData['otp'] !== $request->otp) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid or expired OTP. Please request a new one.');
        }

        // OTP verified - create a reset token and send reset link to email
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

        // Send password reset link to email
        $resetUrl = route('admin.password.reset', [
            'token' => $token,
            'email' => $admin->email,
        ]);
        
        // Log communication BEFORE sending (so it's logged immediately, not in queue)
        try {
            $subject = 'Password Reset Instructions - ' . config('app.name');
            $messageContent = "Hello {$admin->full_name}!\n\n";
            $messageContent .= "You have successfully verified your identity using the OTP sent to your phone.\n";
            $messageContent .= "Click the link below to reset your password. This link will expire in 1 hour.\n\n";
            $messageContent .= "Reset Password: {$resetUrl}\n\n";
            $messageContent .= "If you did not request a password reset, please ignore this email or contact support if you have concerns.\n\n";
            $messageContent .= "Security Note: This link can only be used once.";

            \App\Support\CommunicationLogger::log(
                subject: $subject,
                message: $messageContent,
                type: 'email',
                isSensitive: true, // Contains reset token/URL
                recipient: $admin,
                createdBy: null, // User-initiated, not admin-initiated
                metadata: [
                    'notification_type' => 'password_reset',
                    'is_admin_initiated' => false,
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to log password reset communication', [
                'error' => $e->getMessage(),
                'admin_id' => $admin->id ?? null,
            ]);
        }
        
        $admin->notify(new \App\Notifications\AdminPasswordResetLink($resetUrl, false));

        return redirect()->route('admin.password.forgot')
            ->with('success', 'OTP verified successfully! A password reset link has been sent to your email address. Please check your inbox and follow the instructions to reset your password.');
    }

    /**
     * Display the password reset form.
     */
    public function showResetForm(Request $request, string $token): View
    {
        $email = $request->query('email');

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Handle password reset.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return redirect()->route('admin.password.forgot')
                ->with('error', 'Invalid email address.');
        }

        $resetTokenKey = 'admin_password_reset_token_' . $admin->id;
        $cachedData = Cache::get($resetTokenKey);

        if (!$cachedData || $cachedData['token'] !== $request->token) {
            return redirect()->route('admin.password.forgot')
                ->with('error', 'Invalid or expired reset token. Please request a new password reset.');
        }

        // Update password
        $admin->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ])->save();

        // Clear reset token from cache (one-time use)
        Cache::forget($resetTokenKey);

        return redirect()->route('admin.login')
            ->with('success', 'Your password has been reset successfully. You can now login with your new password.');
    }
}

