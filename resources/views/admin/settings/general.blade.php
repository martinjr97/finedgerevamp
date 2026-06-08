@extends('layouts.admin')

@section('title', 'General Settings | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'General Settings',
            'description' => 'High-level system behaviours and feature toggles.',
        ])

        @php
            $setting = $setting ?? new \App\Models\GeneralSetting();
        @endphp

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Customer Registration</h2>
                        <p class="text-xs text-slate-400 mt-1">
                            Control whether customers can request to register and which products/groups are exposed.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ ($setting->allow_customer_registration ?? false) ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-300' }}">
                        {{ ($setting->allow_customer_registration ?? false) ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="pt-2">
                    <a href="{{ route('admin.settings.customer-registration.edit') }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 hover:from-cyan-600 hover:to-blue-700 transition">
                        Manage
                    </a>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Repayment Reminders</h2>
                        <p class="text-xs text-slate-400 mt-1">
                            Configure automated reminders for upcoming and missed loan repayments.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ ($setting->repayment_reminders_enabled ?? false) ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-300' }}">
                        {{ ($setting->repayment_reminders_enabled ?? false) ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="pt-2">
                    <a href="{{ route('admin.settings.repayment-reminders.edit') }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 hover:from-cyan-600 hover:to-blue-700 transition">
                        Manage
                    </a>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Credit Score Engine</h2>
                        <p class="text-xs text-slate-400 mt-1">
                            Configure automatic loan limit adjustment based on internal credit scores.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ ($setting->auto_adjust_loan_limit_by_credit_score ?? false) ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-500/20 text-slate-300' }}">
                        {{ ($setting->auto_adjust_loan_limit_by_credit_score ?? false) ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="pt-2">
                    <a href="{{ route('admin.settings.credit-score.edit') }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 hover:from-cyan-600 hover:to-blue-700 transition">
                        Manage
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
