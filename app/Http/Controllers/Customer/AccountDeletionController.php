<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function __construct(
        private AccountDeletionService $deletionService
    ) {}

    /**
     * Show the account deletion page.
     * Public URL for Google Play. Unauthenticated: login form. Authenticated: account info + confirm deletion.
     */
    public function show(): View
    {
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();

        return view('customer.account-delete', [
            'customer' => $customer,
            'isLoggedIn' => $customer !== null,
        ]);
    }

    /**
     * Log in on this page only (phone + PIN). Redirects back to account delete page to confirm.
     * Does not redirect to PIN change or security questions; user is here only to delete account.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4'],
        ]);

        $phone = preg_replace('/\D/', '', $credentials['phone']);
        $customer = Customer::where(function ($query) use ($phone) {
            $query->where('phone', $phone)
                ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
        })->first();

        if (! $customer || ! Hash::check($credentials['pin'], $customer->password)) {
            return redirect()
                ->route('customer.account.delete')
                ->withInput($request->only('phone'))
                ->with('error', 'The phone number or PIN you entered is incorrect.');
        }

        if ($customer->status !== 'active') {
            return redirect()
                ->route('customer.account.delete')
                ->withInput($request->only('phone'))
                ->with('error', 'Your account is not active. Please contact support.');
        }

        Auth::guard('customer')->login($customer, false);
        $request->session()->regenerate();

        return redirect()->route('customer.account.delete');
    }

    /**
     * Log out from the account-deletion flow and return to the login form.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.account.delete');
    }

    /**
     * Permanently anonymize and close the current customer's account.
     * Only the authenticated customer can delete their own account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();

        if (! $customer instanceof Customer) {
            return redirect()
                ->route('customer.account.delete')
                ->with('error', 'You must be logged in to delete your account.');
        }

        $request->validate([
            'confirm_phrase' => ['required', 'string', 'in:DELETE MY ACCOUNT'],
            'confirm_checkbox' => ['required', 'accepted'],
        ], [
            'confirm_phrase.in' => 'Please type exactly: DELETE MY ACCOUNT',
            'confirm_checkbox.accepted' => 'You must confirm that you understand this action is permanent.',
        ]);

        $this->deletionService->deleteAccount($customer);

        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('customer.account.deleted')
            ->with('status', 'Your account has been permanently deleted.');
    }
}
