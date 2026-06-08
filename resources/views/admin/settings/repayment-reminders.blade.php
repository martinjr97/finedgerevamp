@extends('layouts.admin')

@section('title', 'Repayment Reminder Settings | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Repayment Reminder Settings',
            'description' => 'Configure automated reminders for upcoming and missed loan repayments.',
        ])

        @php
            $setting = $setting ?? new \App\Models\GeneralSetting();
        @endphp

        <form action="{{ route('admin.settings.repayment-reminders.update') }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>
                    Enable Repayment Reminders
                </h2>

                <div class="space-y-4">
                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="repayment_reminders_enabled" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('repayment_reminders_enabled', $setting->repayment_reminders_enabled ?? false))>
                        <span>
                            <span class="font-semibold">Enable automated repayment reminders</span>
                            <span class="block text-xs text-slate-400">
                                When enabled, the system will automatically send reminders to customers about upcoming and missed loan repayments. Reminders run daily at 09:00.
                            </span>
                        </span>
                    </label>
                    @error('repayment_reminders_enabled')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>
                    Upcoming Payment Reminders
                </h2>
                <p class="text-sm text-slate-400">
                    Select when to send reminders before the payment due date. You can enable multiple options.
                </p>

                <div class="space-y-4">
                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="remind_1_week_before" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('remind_1_week_before', $setting->remind_1_week_before ?? false))>
                        <span>
                            <span class="font-semibold">Remind 1 week before due date</span>
                            <span class="block text-xs text-slate-400">
                                Send a reminder 7 days before the payment is due.
                            </span>
                        </span>
                    </label>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="remind_2_days_before" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('remind_2_days_before', $setting->remind_2_days_before ?? false))>
                        <span>
                            <span class="font-semibold">Remind 2 days before due date</span>
                            <span class="block text-xs text-slate-400">
                                Send a reminder 2 days before the payment is due.
                            </span>
                        </span>
                    </label>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="remind_1_day_before" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('remind_1_day_before', $setting->remind_1_day_before ?? false))>
                        <span>
                            <span class="font-semibold">Remind 1 day before due date</span>
                            <span class="block text-xs text-slate-400">
                                Send a reminder 1 day before the payment is due.
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>
                    Missed Payment Reminders
                </h2>
                <p class="text-sm text-slate-400">
                    Configure how many reminders to send for missed payments. The first reminder is sent 1 day after the due date, and the second (if enabled) is sent 3 days after the due date.
                </p>

                <div class="space-y-4">
                    <label class="block text-sm text-slate-200">
                        <span class="font-semibold mb-2 block">Number of missed payment reminders</span>
                        <select name="missed_payment_reminder_count" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                            <option value="0" @selected(old('missed_payment_reminder_count', $setting->missed_payment_reminder_count ?? 0) == 0)>None (0)</option>
                            <option value="1" @selected(old('missed_payment_reminder_count', $setting->missed_payment_reminder_count ?? 0) == 1)>One reminder (1)</option>
                            <option value="2" @selected(old('missed_payment_reminder_count', $setting->missed_payment_reminder_count ?? 0) == 2)>Two reminders (2)</option>
                        </select>
                        <span class="block text-xs text-slate-400 mt-1">
                            Select how many reminders to send for missed payments. Reminders include the amount due and days overdue.
                        </span>
                    </label>
                    @error('missed_payment_reminder_count')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('admin.settings.general.edit') }}" class="inline-flex items-center gap-2 rounded-2xl bg-white/10 border border-white/20 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-white/20 transition">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 hover:from-cyan-600 hover:to-blue-700 transition">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
@endsection

