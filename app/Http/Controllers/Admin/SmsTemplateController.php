<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use App\Sms\Enums\SmsCategory;
use App\Sms\Services\SmsTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SmsTemplateController extends Controller
{
    public function index(): View
    {
        abort_unless(
            auth('admin')->user()?->can('sms-operations.view')
            || auth('admin')->user()?->can('sms-operations.manage'),
            403
        );

        $templates = SmsTemplate::query()->orderBy('name')->get();

        return view('admin.sms-operations.templates.index', [
            'templates' => $templates,
        ]);
    }

    public function edit(SmsTemplate $smsTemplate): View
    {
        abort_unless(auth('admin')->user()?->can('sms-operations.manage'), 403);

        return view('admin.sms-operations.templates.edit', [
            'template' => $smsTemplate,
            'categories' => SmsCategory::cases(),
            'samplePreview' => app(SmsTemplateService::class)->render($smsTemplate->key, $this->sampleVariables()),
        ]);
    }

    public function update(Request $request, SmsTemplate $smsTemplate, SmsTemplateService $templateService): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('sms-operations.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'max_length' => ['required', 'integer', 'min:70', 'max:500'],
            'category' => ['required', Rule::enum(SmsCategory::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (mb_strlen($validated['body']) > (int) $validated['max_length']) {
            return back()
                ->withInput()
                ->withErrors(['body' => 'Template body cannot exceed the configured max length.']);
        }

        $smsTemplate->update([
            'name' => $validated['name'],
            'body' => $validated['body'],
            'max_length' => (int) $validated['max_length'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $preview = $templateService->render($smsTemplate->key, $this->sampleVariables());
        if ($preview === null) {
            return redirect()
                ->route('admin.sms-templates.edit', $smsTemplate)
                ->with('warning', 'Template saved, but sample preview exceeds max length. Shorten placeholders or text.');
        }

        return redirect()
            ->route('admin.sms-templates.index')
            ->with('status', 'SMS template updated successfully.');
    }

    /**
     * @return array<string, string>
     */
    private function sampleVariables(): array
    {
        return [
            'name' => 'John',
            'phone' => '260971234567',
            'pin' => '1234',
            'amount' => '500',
            'balance' => '1,200',
            'loan_number' => 'LN-00001',
            'repayment_number' => 'RP-00001',
            'due_date' => '15 Jul 2026',
            'reference' => 'REF-001',
            'days_overdue' => '3',
            'app_name' => (string) config('app.name', 'FineEdge'),
        ];
    }
}
