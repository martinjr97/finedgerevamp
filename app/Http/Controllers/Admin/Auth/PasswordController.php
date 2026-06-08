<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.auth.force-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $admin = $request->user('admin');

            $admin->forceFill([
                'password' => Hash::make($validator->validated()['password']),
                'must_change_password' => false,
            ])->save();

            return redirect()
                ->route('admin.dashboard')
                ->with('status', 'Password updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to update password: '.$e->getMessage())
                ->withInput();
        }
    }
}
