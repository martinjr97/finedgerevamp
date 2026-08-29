@extends('layouts.admin')

@section('title', 'Edit Expense Category | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Expense Category',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back',
                'href' => route('admin.financial-categories.index'),
            ]],
        ])

        <form action="{{ route('admin.financial-categories.expense.update', $expenseCategory) }}" method="POST" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
            @csrf
            @method('PUT')
            @include('admin.financial-categories.partials.category-fields', ['model' => $expenseCategory])
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 font-semibold text-white">Update Category</button>
            </div>
        </form>
        @can('financial-categories.delete')
            @if (! $expenseCategory->transactions()->exists())
                <form action="{{ route('admin.financial-categories.expense.destroy', $expenseCategory) }}" method="POST" class="max-w-2xl" onsubmit="return confirm('Delete this expense category?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-2xl border border-rose-400/50 bg-rose-500/10 px-6 py-3 font-semibold text-rose-200">Delete Category</button>
                </form>
            @endif
        @endcan

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg max-w-2xl">
            <h3 class="text-lg font-semibold text-white mb-4">Subcategories</h3>
            <ul class="space-y-2 text-sm text-slate-300">
                @foreach ($expenseCategory->subcategories as $subcategory)
                    <li class="flex items-center justify-between rounded-xl border border-white/10 px-4 py-2">
                        <span>{{ $subcategory->name }} <span class="text-xs text-slate-500">({{ $subcategory->code ?: 'no code' }})</span></span>
                        <span class="{{ $subcategory->is_active ? 'text-emerald-400' : 'text-rose-400' }}">{{ $subcategory->is_active ? 'Active' : 'Inactive' }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-slate-500">Subcategories are migrated from the legacy system. Additional subcategory management can be added later if needed.</p>
        </div>
    </div>
@endsection
