@extends('layouts.admin')

@section('title', $expenseCategory->name.' | Financial Categories | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $expenseCategory->name,
            'description' => 'Expense category and subcategories used when classifying treasury expenses.',
            'buttons' => array_filter([
                [
                    'action' => 'back',
                    'text' => 'Back to Categories',
                    'href' => route('admin.financial-categories.index'),
                ],
                auth('admin')->user()?->can('financial-categories.update') ? [
                    'action' => 'edit',
                    'text' => 'Edit Category',
                    'href' => route('admin.financial-categories.expense.edit', $expenseCategory),
                ] : null,
                auth('admin')->user()?->can('financial-categories.create') ? [
                    'action' => 'create',
                    'text' => 'Add Subcategory',
                    'href' => route('admin.financial-categories.expense.subcategory.create', $expenseCategory),
                ] : null,
            ]),
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg max-w-3xl space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Category details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-slate-400">Code</dt>
                    <dd class="font-mono text-white mt-1">{{ $expenseCategory->code }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Status</dt>
                    <dd class="mt-1 font-medium {{ $expenseCategory->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $expenseCategory->is_active ? 'Active' : 'Inactive' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-400">Transactions</dt>
                    <dd class="text-white mt-1">{{ number_format($expenseCategory->transactions_count) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Sort order</dt>
                    <dd class="text-white mt-1">{{ $expenseCategory->sort_order }}</dd>
                </div>
                @if ($expenseCategory->description)
                    <div class="sm:col-span-2">
                        <dt class="text-slate-400">Description</dt>
                        <dd class="text-white mt-1">{{ $expenseCategory->description }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @include('admin.financial-categories.partials.expense-subcategory-list', [
            'expenseCategory' => $expenseCategory,
            'headingId' => 'subcategories',
        ])
    </div>
@endsection
