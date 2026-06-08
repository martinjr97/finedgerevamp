@extends('layouts.admin')

@section('title', 'Payment Due Report | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Payment Reports</p>
            <h1 class="text-3xl font-bold">Payment Due Report</h1>
            <p class="text-sm text-slate-400">Select a company and month to generate the payment due report</p>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form action="{{ route('admin.payment-due-report.generate') }}" method="POST" class="space-y-6">
                @csrf
                
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Select Company</label>
                    <select 
                        name="company_id" 
                        required 
                        class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                    >
                        <option value="">Select a company</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
                                {{ $company->name }} {{ $company->pay_day ? '(Pay Day: ' . $company->pay_day . ')' : '(No Pay Day Set)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')
                        <p class="text-sm text-rose-300 mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Select Month</label>
                    <input 
                        type="month" 
                        name="month" 
                        value="{{ old('month', now()->format('Y-m')) }}" 
                        required 
                        class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                        min="{{ now()->subYear()->format('Y-m') }}"
                        max="{{ now()->addYear()->format('Y-m') }}"
                    >
                    <p class="mt-2 text-xs text-slate-400">
                        The report will show all loans with payment due dates matching the company's pay day for the selected month.
                    </p>
                    @error('month')
                        <p class="text-sm text-rose-300 mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

