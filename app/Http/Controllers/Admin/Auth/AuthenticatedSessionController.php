<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLoginAudit;
use App\Support\DeviceParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the admin login view.
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = $credentials['email'];
        $admin = Admin::where('email', $email)->first();
        $failureReason = null;

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            // Parse device and location
            $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
            $locationInfo = DeviceParser::getLocationFromIp($request->ip());
            
            // Log failed login attempt
            AdminLoginAudit::create(array_merge([
                'admin_id' => $admin?->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => $admin ? 'invalid_credentials' : 'email_not_found',
                'attempted_at' => now(),
            ], $deviceInfo, $locationInfo));

            return redirect()->back()
                ->withInput($request->only('email'))
                ->with('error', 'The provided credentials do not match our records.');
        }

        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();

        // Check if admin is active
        if (!$admin->is_active) {
            // Parse device and location
            $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
            $locationInfo = DeviceParser::getLocationFromIp($request->ip());
            
            // Log failed login attempt
            AdminLoginAudit::create(array_merge([
                'admin_id' => $admin->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => 'account_inactive',
                'attempted_at' => now(),
            ], $deviceInfo, $locationInfo));

            Auth::guard('admin')->logout();
            return redirect()->back()
                ->withInput($request->only('email'))
                ->with('error', 'Your account is inactive. Please contact support.');
        }

        // Check if admin is approved
        if ($admin->approval_status !== 'approved') {
            // Parse device and location
            $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
            $locationInfo = DeviceParser::getLocationFromIp($request->ip());
            
            // Log failed login attempt
            AdminLoginAudit::create(array_merge([
                'admin_id' => $admin->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => 'account_pending',
                'attempted_at' => now(),
            ], $deviceInfo, $locationInfo));

            Auth::guard('admin')->logout();
            return redirect()->back()
                ->withInput($request->only('email'))
                ->with('error', 'Your account is pending approval.');
        }

        // Parse device and location
        $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
        $locationInfo = DeviceParser::getLocationFromIp($request->ip());
        
        // Log successful login attempt
        AdminLoginAudit::create(array_merge([
            'admin_id' => $admin->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
            'failure_reason' => null,
            'attempted_at' => now(),
        ], $deviceInfo, $locationInfo));

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if ($admin->must_change_password) {
            return redirect()->route('admin.password.edit');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
