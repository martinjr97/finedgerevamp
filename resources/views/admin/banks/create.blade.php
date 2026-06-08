@extends('layouts.admin')

@section('title', 'Add Bank | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Financial Management</p>
            <h1 class="text-3xl font-bold">Add bank account</h1>
            <p class="text-sm text-slate-400">Link a company treasury account for disbursements and repayments.</p>
        </div>

        <form action="{{ route('admin.banks.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                @include('partials.admin.bank-form-fields', [
                    'financialInstitutions' => $financialInstitutions,
                ])
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.banks.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    Create bank account
                </button>
            </div>
        </form>
    </div>
@endsection
