<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinController extends Controller
{
    /**
     * Show the form for editing the customer's PIN.
     */
    public function edit(): View
    {
        $customer = Auth::guard('customer')->user();
        
        return view('customer.auth.change-pin', [
            'mustChangePin' => $customer->must_change_pin,
        ]);
    }

    /**
     * Update the customer's PIN.
     */
    public function update(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $rules = [
            'new_pin' => ['required', 'string', 'size:4'],
            'new_pin_confirmation' => ['required', 'string', 'size:4', 'same:new_pin'],
        ];

        $messages = [
            'new_pin.required' => 'Please enter a new PIN.',
            'new_pin.size' => 'PIN must be exactly 4 digits.',
            'new_pin_confirmation.required' => 'Please confirm your new PIN.',
            'new_pin_confirmation.same' => 'PIN confirmation does not match.',
        ];

        // Only require current PIN if not forced to change
        if (!$customer->must_change_pin) {
            $rules['current_pin'] = ['required', 'string', 'size:4'];
            $messages['current_pin.required'] = 'Please enter your current PIN.';
            $messages['current_pin.size'] = 'PIN must be exactly 4 digits.';
        }

        $validated = $request->validate($rules, $messages);

        // Verify current PIN only if not forced to change
        if (!$customer->must_change_pin) {
            if (!Hash::check($validated['current_pin'], $customer->password)) {
                throw ValidationException::withMessages([
                    'current_pin' => __('The current PIN is incorrect.'),
                ]);
            }
            
            // Check if new PIN is different from current
            if (Hash::check($validated['new_pin'], $customer->password)) {
                throw ValidationException::withMessages([
                    'new_pin' => __('New PIN must be different from your current PIN.'),
                ]);
            }
        }

        // Update PIN (stored in password field)
        $customer->update([
            'password' => Hash::make($validated['new_pin']),
            'must_change_pin' => false,
        ]);

        return redirect()
            ->route('customer.dashboard')
            ->with('status', 'Your PIN has been changed successfully.');
    }
}
