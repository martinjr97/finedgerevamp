<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLoginAudit;
use App\Models\GeneralSetting;
use App\Support\DeviceParser;
use App\Support\PhoneNumberFormatter;
use App\Support\ZambianPhoneRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the customer login page.
     */
    public function create(): View
    {
        $setting = GeneralSetting::query()->first();

        return view('customer.auth.login', [
            'authSideImage' => 'homepage.png',
            'brandColor' => 'text-emerald-600',
            'allowCustomerRegistration' => (bool) optional($setting)->allow_customer_registration,
        ]);
    }

    /**
     * Attempt to authenticate the customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            [
                'phone' => ZambianPhoneRules::required(),
                'pin' => ['required', 'string', 'size:4'],
            ],
            ZambianPhoneRules::messages(),
            ZambianPhoneRules::attributes()
        );

        $phone = PhoneNumberFormatter::digitsOnly($credentials['phone']) ?? '';

        $customer = $this->findCustomerByPhone($phone);

        if (!$customer || !Hash::check($credentials['pin'], $customer->password)) {
            // Parse device and location
            $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
            $locationInfo = DeviceParser::getLocationFromIp($request->ip());
            
            // Log failed login attempt
            CustomerLoginAudit::create(array_merge([
                'customer_id' => $customer?->id,
                'phone' => $phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => $customer ? 'invalid_credentials' : 'phone_not_found',
                'attempted_at' => now(),
            ], $deviceInfo, $locationInfo));
            
            return redirect()->back()
                ->withInput($request->only('phone'))
                ->with('error', 'The provided credentials do not match our records.');
        }

        // Only approved customers can sign in
        if ($customer->approval_status !== 'approved') {
            // Parse device and location
            $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
            $locationInfo = DeviceParser::getLocationFromIp($request->ip());

            // Log failed login attempt
            CustomerLoginAudit::create(array_merge([
                'customer_id' => $customer->id,
                'phone' => $phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => 'account_not_approved',
                'attempted_at' => now(),
            ], $deviceInfo, $locationInfo));

            return redirect()->back()
                ->withInput($request->only('phone'))
                ->with('error', 'Your account has not been approved. Please contact support.');
        }

        // Check if customer is active
        if ($customer->status !== 'active') {
            $isBlocked = in_array($customer->status, ['suspended', 'blocked'], true);

            // Parse device and location
            $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
            $locationInfo = DeviceParser::getLocationFromIp($request->ip());
            
            // Log failed login attempt
            CustomerLoginAudit::create(array_merge([
                'customer_id' => $customer->id,
                'phone' => $phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => $isBlocked ? 'account_blocked' : 'account_inactive',
                'attempted_at' => now(),
            ], $deviceInfo, $locationInfo));
            
            return redirect()->back()
                ->withInput($request->only('phone'))
                ->with('error', $isBlocked
                    ? 'Your account is blocked. Please contact support.'
                    : 'Your account is not active. Please contact support.');
        }

        // Parse device and location
        $deviceInfo = DeviceParser::parse($request->userAgent() ?? '');
        $locationInfo = DeviceParser::getLocationFromIp($request->ip());
        
        // Log successful login attempt
        CustomerLoginAudit::create(array_merge([
            'customer_id' => $customer->id,
            'phone' => $phone,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
            'failure_reason' => null,
            'attempted_at' => now(),
        ], $deviceInfo, $locationInfo));

        // Log the customer in
        Auth::guard('customer')->login($customer, $request->boolean('remember'));

        $request->session()->regenerate();

        $customer->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        // Redirect to PIN change if required
        if ($customer->must_change_pin) {
            return redirect()->route('customer.pin.edit');
        }

        // Redirect to security questions setup if not set
        if (!$customer->security_question_id || !$customer->security_answer) {
            return redirect()->route('customer.security-questions.setup');
        }

        return redirect()->intended(route('customer.dashboard'));
    }

    /**
     * Log the customer out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }

    private function findCustomerByPhone(string $phone): ?Customer
    {
        $candidates = PhoneNumberFormatter::lookupCandidates($phone);

        if ($candidates === []) {
            return null;
        }

        return Customer::query()
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query->orWhere('phone', $candidate)
                        ->orWhereRaw(
                            'REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?',
                            [$candidate]
                        );
                }
            })
            ->first();
    }
}
