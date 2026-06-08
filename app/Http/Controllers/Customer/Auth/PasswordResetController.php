<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SecurityQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Display the password reset request form.
     */
    public function showForgotPasswordForm(): View
    {
        return view('customer.auth.forgot-password', [
            'authSideImage' => 'homepage.png',
            'brandColor' => 'text-emerald-600',
        ]);
    }

    /**
     * Handle password reset request - verify phone and national ID, send OTP.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $request->phone);

        // Find customer by phone and national ID
        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $request->national_id)
        ->first();

        if (!$customer) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'We could not find an account matching the provided phone number and National ID.');
        }

        if (!$customer->phone) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account does not have a phone number registered. Please contact support.');
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in cache for 10 minutes
        $cacheKey = 'customer_password_reset_otp_' . $customer->id;
        Cache::put($cacheKey, [
            'otp' => $otp,
            'phone' => $phone,
            'national_id' => $request->national_id,
            'expires_at' => now()->addMinutes(10),
        ], now()->addMinutes(10));

        // Send OTP via SMS
        // TODO: Integrate your SMS service here
        \Log::info('Customer Password Reset OTP Sent to Phone', [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'national_id' => $customer->national_id,
            'otp' => $otp,
        ]);

        // Log communication (with sensitive data masked)
        $message = "Your password reset OTP is: {$otp}. Valid for 10 minutes.";
        try {
            \App\Support\CommunicationLogger::log(
                subject: 'Password Reset OTP - ' . config('app.name'),
                message: $message,
                type: 'sms',
                isSensitive: true,
                recipient: $customer,
                createdBy: null, // System generated
                metadata: ['notification_type' => 'password_reset_otp']
            );
        } catch (\Exception $e) {
            \Log::error('Failed to log OTP communication', ['error' => $e->getMessage()]);
        }

        return redirect()->route('customer.password.verify-otp')
            ->with('status', 'An OTP has been sent to your registered phone number. Please check your phone and enter the code.')
            ->with('phone', $phone)
            ->with('national_id', $request->national_id);
    }

    /**
     * Display the OTP verification form.
     */
    public function showVerifyOtpForm(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('phone') ?? old('phone') ?? $request->query('phone');
        $nationalId = $request->session()->get('national_id') ?? old('national_id') ?? $request->query('national_id');
        
        if (!$phone || !$nationalId) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Please request a password reset first.');
        }

        // Ensure values are in session for form submission
        $request->session()->put('phone', $phone);
        $request->session()->put('national_id', $nationalId);

        return view('customer.auth.verify-otp', [
            'phone' => $phone,
            'national_id' => $nationalId,
            'authSideImage' => 'homepage.png',
            'brandColor' => 'text-emerald-600',
        ]);
    }

    /**
     * Verify OTP and proceed to security question.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $request->phone);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $request->national_id)
        ->first();

        if (!$customer) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Invalid phone number or National ID.');
        }

        $cacheKey = 'customer_password_reset_otp_' . $customer->id;
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || $cachedData['otp'] !== $request->otp) {
            return redirect()->back()
                ->withInput()
                ->with('phone', $phone)
                ->with('national_id', $request->national_id)
                ->with('error', 'Invalid or expired OTP. Please request a new one.');
        }

        // Check if customer has security question set
        if (!$customer->security_question_id || !$customer->security_answer) {
            return redirect()->back()
                ->withInput()
                ->with('phone', $phone)
                ->with('national_id', $request->national_id)
                ->with('error', 'Security question not set. Please contact support.');
        }

        // OTP verified - proceed to security question
        // Store verification in cache
        $verifyKey = 'customer_password_reset_verified_' . $customer->id;
        Cache::put($verifyKey, [
            'phone' => $phone,
            'national_id' => $request->national_id,
            'expires_at' => now()->addMinutes(15),
        ], now()->addMinutes(15));

        // Clear OTP from cache
        Cache::forget($cacheKey);

        return redirect()->route('customer.password.security-question')
            ->with('phone', $phone)
            ->with('national_id', $request->national_id)
            ->with('status', 'OTP verified successfully. Please answer your security question.');
    }

    /**
     * Display the security question form.
     */
    public function showSecurityQuestionForm(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('phone') ?? old('phone');
        $nationalId = $request->session()->get('national_id') ?? old('national_id');
        
        if (!$phone || !$nationalId) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Please complete the previous steps first.');
        }

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $phone);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $nationalId)
        ->with('securityQuestion')
        ->first();

        if (!$customer || !$customer->security_question_id) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Security question not found. Please contact support.');
        }

        return view('customer.auth.security-question', [
            'phone' => $phone,
            'national_id' => $nationalId,
            'customer' => $customer,
            'securityQuestion' => $customer->securityQuestion,
            'authSideImage' => 'homepage.png',
            'brandColor' => 'text-emerald-600',
        ]);
    }

    /**
     * Verify security question and proceed to reset password.
     */
    public function verifySecurityQuestion(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
            'security_answer' => ['required', 'string'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $request->phone);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $request->national_id)
        ->first();

        if (!$customer) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Invalid phone number or National ID.');
        }

        // Verify OTP was verified
        $verifyKey = 'customer_password_reset_verified_' . $customer->id;
        $verified = Cache::get($verifyKey);

        if (!$verified) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Please complete OTP verification first.');
        }

        // Verify security answer (case-insensitive)
        if (strtolower(trim($customer->security_answer)) !== strtolower(trim($request->security_answer))) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Incorrect security answer. Please try again.');
        }

        // Security question verified - create reset token
        $token = Str::random(64);
        
        // Store reset token in cache for 1 hour
        $resetTokenKey = 'customer_password_reset_token_' . $customer->id;
        Cache::put($resetTokenKey, [
            'token' => $token,
            'phone' => $phone,
            'national_id' => $request->national_id,
            'expires_at' => now()->addHour(),
        ], now()->addHour());

        // Clear verification from cache
        Cache::forget($verifyKey);

        return redirect()->route('customer.password.reset', [
            'token' => $token,
            'phone' => $phone,
            'national_id' => $request->national_id,
        ])->with('status', 'Security question verified successfully. Please set your new PIN.');
    }

    /**
     * Display the password reset form.
     */
    public function showResetForm(Request $request, string $token): View
    {
        $phone = $request->query('phone');
        $nationalId = $request->query('national_id');

        return view('customer.auth.reset-password', [
            'token' => $token,
            'phone' => $phone,
            'national_id' => $nationalId,
            'authSideImage' => 'homepage.png',
            'brandColor' => 'text-emerald-600',
        ]);
    }

    /**
     * Handle password reset.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'national_id' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'confirmed'],
        ]);

        // Normalize phone number
        $phone = preg_replace('/\D/', '', $request->phone);

        $customer = Customer::where(function($query) use ($phone) {
            $query->where('phone', $phone)
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })
        ->where('national_id', $request->national_id)
        ->first();

        if (!$customer) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Invalid phone number or National ID.');
        }

        $resetTokenKey = 'customer_password_reset_token_' . $customer->id;
        $cachedData = Cache::get($resetTokenKey);

        if (!$cachedData || $cachedData['token'] !== $request->token) {
            return redirect()->route('customer.password.forgot')
                ->with('error', 'Invalid or expired reset token. Please request a new password reset.');
        }

        // Update password (PIN)
        $customer->forceFill([
            'password' => Hash::make($request->pin),
            'must_change_pin' => false,
        ])->save();

        // Clear reset token from cache
        Cache::forget($resetTokenKey);

        return redirect()->route('customer.login')
            ->with('success', 'Your PIN has been reset successfully. You can now login with your new PIN.');
    }
}

