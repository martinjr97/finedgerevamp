<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PinController extends Controller
{
    /**
     * Change PIN while authenticated
     */
    public function update(Request $request): JsonResponse
    {
        $customer = $request->user();

        $rules = [
            'new_pin' => ['required', 'string', 'size:4'],
            'new_pin_confirmation' => ['required', 'string', 'size:4', 'same:new_pin'],
        ];

        // Only require current PIN if not forced to change
        if (!$customer->must_change_pin) {
            $rules['current_pin'] = ['required', 'string', 'size:4'];
        }

        $validated = $request->validate($rules);

        // Verify current PIN only if not forced to change
        if (!$customer->must_change_pin) {
            if (!Hash::check($validated['current_pin'], $customer->password)) {
                throw ValidationException::withMessages([
                    'current_pin' => ['The current PIN is incorrect.'],
                ]);
            }
            
            // Check if new PIN is different from current
            if (Hash::check($validated['new_pin'], $customer->password)) {
                throw ValidationException::withMessages([
                    'new_pin' => ['New PIN must be different from your current PIN.'],
                ]);
            }
        }

        // Update PIN (stored in password field)
        $customer->update([
            'password' => Hash::make($validated['new_pin']),
            'must_change_pin' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your PIN has been changed successfully.',
        ]);
    }
}

