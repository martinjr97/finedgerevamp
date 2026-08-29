@extends('layouts.admin')

@section('title', 'Add Expense Subcategory | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Add Expense Subcategory',
            'description' => 'Create a subcategory under '.$expenseCategory->name.'.',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back',
                'href' => route('admin.financial-categories.index'),
            ]],
        ])

        <form action="{{ route('admin.financial-categories.expense.subcategory.store', $expenseCategory) }}" method="POST" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
            @csrf

            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                Parent category: <span class="font-semibold text-white">{{ $expenseCategory->name }}</span>
                <span class="font-mono text-xs text-slate-500">({{ $expenseCategory->code }})</span>
            </div>

            @include('admin.financial-categories.partials.subcategory-fields')

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-emerald-500 to-lime-600 px-6 py-3 font-semibold text-white">Save Subcategory</button>
                <a href="{{ route('admin.financial-categories.expense.edit', $expenseCategory) }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm text-slate-300 hover:bg-white/10 transition">Cancel</a>
            </div>
        </form>
    </div>
@endsection
