<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\View\View;

class GeneralSettingController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth('admin')->user()?->can('settings.view'), 403);

        $setting = GeneralSetting::query()->first() ?? new GeneralSetting([
            'allow_customer_registration' => false,
            'repayment_reminders_enabled' => false,
            'remind_1_week_before' => false,
            'remind_2_days_before' => false,
            'remind_1_day_before' => false,
            'missed_payment_reminder_count' => 0,
            'auto_adjust_loan_limit_by_credit_score' => false,
        ]);

        return view('admin.settings.general', [
            'setting' => $setting,
        ]);
    }
}
