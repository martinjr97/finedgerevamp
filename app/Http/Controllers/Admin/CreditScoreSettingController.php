<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditScoreSettingController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth('admin')->user()?->can('settings.view'), 403);

        $setting = GeneralSetting::query()->first() ?? new GeneralSetting([
            'auto_adjust_loan_limit_by_credit_score' => false,
        ]);

        return view('admin.settings.credit-score', [
            'setting' => $setting,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'auto_adjust_loan_limit_by_credit_score' => ['nullable', 'boolean'],
        ]);

        $setting = GeneralSetting::first();

        if (!$setting) {
            $setting = new GeneralSetting();
        }

        $setting->fill([
            'auto_adjust_loan_limit_by_credit_score' => $request->boolean('auto_adjust_loan_limit_by_credit_score', false),
        ]);

        $setting->save();

        return redirect()
            ->route('admin.settings.credit-score.edit')
            ->with('status', 'Credit score settings updated successfully.');
    }
}
