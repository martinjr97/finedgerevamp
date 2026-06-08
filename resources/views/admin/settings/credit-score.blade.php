@extends('layouts.admin')

@section('title', 'Credit Score Settings | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Credit Score Engine Settings',
            'description' => 'Configure automatic loan limit adjustment based on internal credit scores.',
        ])

        @php
            $setting = $setting ?? new \App\Models\GeneralSetting();
        @endphp

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-cyan-500"></span>
                Credit Score Engine
            </h2>

            <div class="space-y-4">
                <p class="text-sm text-slate-300">
                    The Internal Credit Score Engine calculates a dynamic credit score (0-100) for each customer based on:
                </p>
                <ul class="list-disc list-inside space-y-2 text-sm text-slate-300 ml-4">
                    <li><strong>Payment Punctuality (30%):</strong> On-time vs late payments</li>
                    <li><strong>Loan Completion History (25%):</strong> Percentage of loans fully completed</li>
                    <li><strong>Defaults & Delays (25%):</strong> Current overdue amounts and default history</li>
                    <li><strong>Loan Frequency (10%):</strong> How frequently customer takes loans</li>
                    <li><strong>Loan Size Growth (10%):</strong> Responsible growth in loan amounts</li>
                </ul>
            </div>
        </div>

        <form action="{{ route('admin.settings.credit-score.update') }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>
                    Automatic Loan Limit Adjustment
                </h2>

                <div class="space-y-4">
                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="auto_adjust_loan_limit_by_credit_score" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('auto_adjust_loan_limit_by_credit_score', $setting->auto_adjust_loan_limit_by_credit_score ?? false))>
                        <span>
                            <span class="font-semibold">Automatically adjust maximum loan limit based on credit score</span>
                            <span class="block text-xs text-slate-400 mt-1">
                                When enabled, the system will automatically adjust each customer's <code class="text-cyan-300">maximum_loan_take</code> based on their credit score. 
                                The limit cannot exceed 60% of net salary, but can be reduced below 60% for customers with lower credit scores.
                                <br><br>
                                <strong>Scoring:</strong>
                                <br>• Score 80-100 (Excellent/Good): 100% of base limit (60% of salary)
                                <br>• Score 60-79 (Fair): 60-80% of base limit
                                <br>• Score 50-59 (Poor): 50-60% of base limit
                                <br>• Score 0-49 (Very Poor): 20-50% of base limit (minimum 12% of salary)
                            </span>
                        </span>
                    </label>
                    @error('auto_adjust_loan_limit_by_credit_score')
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

