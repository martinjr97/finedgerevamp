<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepaymentReminderSettingController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth('admin')->user()?->can('settings.view'), 403);

        $setting = GeneralSetting::query()->first() ?? new GeneralSetting([
            'repayment_reminders_enabled' => false,
            'remind_1_week_before' => false,
            'remind_2_days_before' => false,
            'remind_1_day_before' => false,
            'missed_payment_reminder_count' => 0,
        ]);

        return view('admin.settings.repayment-reminders', [
            'setting' => $setting,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'repayment_reminders_enabled' => ['nullable', 'boolean'],
            'remind_1_week_before' => ['nullable', 'boolean'],
            'remind_2_days_before' => ['nullable', 'boolean'],
            'remind_1_day_before' => ['nullable', 'boolean'],
            'missed_payment_reminder_count' => ['nullable', 'integer', 'min:0', 'max:2'],
        ]);

        $setting = GeneralSetting::first();

        if (!$setting) {
            $setting = new GeneralSetting();
        }

        $setting->fill([
            'repayment_reminders_enabled' => $request->boolean('repayment_reminders_enabled', false),
            'remind_1_week_before' => $request->boolean('remind_1_week_before', false),
            'remind_2_days_before' => $request->boolean('remind_2_days_before', false),
            'remind_1_day_before' => $request->boolean('remind_1_day_before', false),
            'missed_payment_reminder_count' => (int) ($validated['missed_payment_reminder_count'] ?? 0),
        ]);

        $setting->save();

        return redirect()
            ->route('admin.settings.repayment-reminders.edit')
            ->with('status', 'Repayment reminder settings updated successfully.');
    }
}
