<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SecurityQuestionController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('security-questions.view'), 403);

        $questions = SecurityQuestion::orderBy('sort_order')
            ->orderBy('question')
            ->get();

        return view('admin.security-questions.index', compact('questions'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('security-questions.create'), 403);

        return view('admin.security-questions.create');
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('security-questions.create'), 403);

        try {
            $data = request()->validate([
                'question' => 'required|string|max:500',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            SecurityQuestion::create($data);

            return redirect()
                ->route('admin.security-questions.index')
                ->with('status', 'Security question created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.security-questions.create')
                ->withInput()
                ->with('error', 'Failed to create security question: '.$e->getMessage());
        }
    }

    public function edit(SecurityQuestion $securityQuestion): View
    {
        abort_unless(auth('admin')->user()?->can('security-questions.update'), 403);

        return view('admin.security-questions.edit', compact('securityQuestion'));
    }

    public function update(SecurityQuestion $securityQuestion): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('security-questions.update'), 403);

        try {
            $data = request()->validate([
                'question' => 'required|string|max:500',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            $securityQuestion->update($data);

            return redirect()
                ->route('admin.security-questions.edit', $securityQuestion)
                ->with('status', 'Security question updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.security-questions.edit', $securityQuestion)
                ->withInput()
                ->with('error', 'Failed to update security question: '.$e->getMessage());
        }
    }

    public function destroy(SecurityQuestion $securityQuestion): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('security-questions.delete'), 403);

        try {
            $securityQuestion->delete();

            return redirect()
                ->route('admin.security-questions.index')
                ->with('status', 'Security question deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.security-questions.index')
                ->with('error', 'Failed to delete security question: '.$e->getMessage());
        }
    }
}
