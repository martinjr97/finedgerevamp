@extends('layouts.admin')

@section('title', 'Create Expense Category | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Expense Category',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back',
                'href' => route('admin.financial-categories.index'),
            ]],
        ])

        <form action="{{ route('admin.financial-categories.expense.store') }}" method="POST" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
            @csrf
            @include('admin.financial-categories.partials.category-fields', ['type' => 'expense'])
            <button type="submit" class="rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 font-semibold text-white">Save Category</button>
        </form>
    </div>
@endsection
