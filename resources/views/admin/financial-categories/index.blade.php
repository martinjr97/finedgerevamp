@extends('layouts.admin')

@section('title', 'Financial Categories | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Financial Categories',
            'description' => 'Manage income and expense categories used when recording treasury transactions.',
            'buttons' => [
                [
                    'action' => 'back',
                    'text' => 'Back to Transactions',
                    'href' => route('admin.financial-transactions.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
                [
                    'action' => 'create',
                    'text' => 'Add Expense Category',
                    'href' => route('admin.financial-categories.expense.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('financial-categories.create'),
                ],
                [
                    'action' => 'create',
                    'text' => 'Add Income Category',
                    'href' => route('admin.financial-categories.income.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('financial-categories.create'),
                    'class' => 'inline-flex items-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500/10 px-3 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/20 transition',
                ],
            ],
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">Expense Categories</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-slate-400">{{ $expenseCategories->count() }} total</span>
            </div>
            <div class="admin-data-table">
                <table class="min-w-full w-full" data-datatable="true" data-datatable-per-page="10" data-datatable-search-placeholder="Search expense categories…">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Subcategories</th>
                            <th scope="col">Transactions</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenseCategories as $category)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $category->name }}</td>
                                <td class="font-mono text-xs text-slate-300">{{ $category->code }}</td>
                                <td>{{ $category->subcategories_count }}</td>
                                <td>{{ $category->transactions_count }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $category->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex flex-wrap items-center justify-center gap-2">
                                        @can('financial-categories.create')
                                            <a href="{{ route('admin.financial-categories.expense.subcategory.create', $category) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400/50 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-200 hover:bg-emerald-500/20 transition">
                                                Add Subcategory
                                            </a>
                                        @endcan
                                        @can('financial-categories.update')
                                            <a href="{{ route('admin.financial-categories.expense.edit', $category) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200 hover:bg-purple-500/20 transition">
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No expense categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-white">Income Categories</h2>
                <span class="text-xs uppercase tracking-[0.3em] text-slate-400">{{ $incomeCategories->count() }} total</span>
            </div>
            <div class="admin-data-table">
                <table class="min-w-full w-full" data-datatable="true" data-datatable-per-page="10" data-datatable-search-placeholder="Search income categories…">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Transactions</th>
                            <th scope="col">Type</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($incomeCategories as $category)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $category->name }}</td>
                                <td class="font-mono text-xs text-slate-300">{{ $category->code }}</td>
                                <td>{{ $category->transactions_count }}</td>
                                <td>
                                    <span class="text-xs font-medium {{ $category->is_system ? 'text-cyan-300' : 'text-slate-300' }}">
                                        {{ $category->is_system ? 'System' : 'Manual' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm font-medium {{ $category->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        @can('financial-categories.update')
                                            <a href="{{ route('admin.financial-categories.income.edit', $category) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-200 hover:bg-purple-500/20 transition">
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No income categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
