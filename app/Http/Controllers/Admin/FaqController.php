<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        abort_unless(auth('admin')->user()?->can('faqs.view'), 403);

        $faqs = Faq::orderByDesc('created_at')->paginate(20);

        return view('admin.faqs.index', compact('faqs'));
    }

    public function create(): \Illuminate\View\View
    {
        abort_unless(auth('admin')->user()?->can('faqs.create'), 403);

        $faq = new Faq();

        return view('admin.faqs.create', [
            'faq' => $faq,
            'visibilities' => Faq::visibilities(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('faqs.create'), 403);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(Faq::visibilities())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        Faq::create($data);

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', 'FAQ created successfully.');
    }

    public function edit(Faq $faq): \Illuminate\View\View
    {
        abort_unless(auth('admin')->user()?->can('faqs.update'), 403);

        return view('admin.faqs.edit', [
            'faq' => $faq,
            'visibilities' => Faq::visibilities(),
        ]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('faqs.update'), 403);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in(Faq::visibilities())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $faq->update($data);

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', 'FAQ updated successfully.');
    }
}


