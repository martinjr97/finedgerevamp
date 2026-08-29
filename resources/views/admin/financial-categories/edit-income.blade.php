@extends('layouts.admin')

@section('title', 'Edit Income Category | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Income Category',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back',
                'href' => route('admin.financial-categories.index'),
            ]],
        ])

        <form action="{{ route('admin.financial-categories.income.update', $incomeCategory) }}" method="POST" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
            @csrf
            @method('PUT')
            @include('admin.financial-categories.partials.category-fields', ['model' => $incomeCategory])
            @if ($incomeCategory->is_system)
                <p class="text-xs text-cyan-300/80">This is a system category used by automated loan income flows. You can rename it for display, but keep the code stable.</p>
            @endif
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-3 font-semibold text-white">Update Category</button>
            </div>
        </form>
        @can('financial-categories.delete')
            @if (! $incomeCategory->is_system && ! $incomeCategory->transactions()->exists())
                <form action="{{ route('admin.financial-categories.income.destroy', $incomeCategory) }}" method="POST" class="max-w-2xl" onsubmit="return confirm('Delete this income category?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-2xl border border-rose-400/50 bg-rose-500/10 px-6 py-3 font-semibold text-rose-200">Delete Category</button>
                </form>
            @endif
        @endcan
    </div>
@endsection
