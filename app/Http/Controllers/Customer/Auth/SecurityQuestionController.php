<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SecurityQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityQuestionController extends Controller
{
    /**
     * Display the security questions setup form.
     */
    public function create(): View
    {
        $securityQuestions = SecurityQuestion::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('customer.auth.setup-security-questions', [
            'securityQuestions' => $securityQuestions,
            'authSideImage' => 'homepage.png',
            'brandColor' => 'text-emerald-600',
        ]);
    }

    /**
     * Store security questions.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'security_question_id' => ['required', 'exists:security_questions,id'],
            'security_answer' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $customer = auth('customer')->user();

        if (!$customer) {
            return redirect()->route('customer.login')
                ->with('error', 'You must be logged in to set security questions.');
        }

        // Verify the security question is active
        $securityQuestion = SecurityQuestion::where('id', $request->security_question_id)
            ->where('is_active', true)
            ->first();

        if (!$securityQuestion) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid security question selected.');
        }

        // Update customer security question
        $customer->forceFill([
            'security_question_id' => $request->security_question_id,
            'security_answer' => trim($request->security_answer),
        ])->save();

        return redirect()->route('customer.dashboard')
            ->with('success', 'Security question has been set successfully. You can now use it for password recovery.');
    }
}

