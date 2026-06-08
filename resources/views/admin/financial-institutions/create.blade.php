@extends('layouts.admin')

@section('title', 'Add Financial Institution | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Add Financial Institution',
            'buttons' => [
                ['action' => 'secondary', 'text' => 'Back', 'href' => route('admin.financial-institutions.index')],
            ],
        ])

        <form method="POST" action="{{ route('admin.financial-institutions.store') }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6 max-w-2xl">
            @csrf
            <div>
                <label class="text-sm font-medium text-slate-200">Name <span class="text-rose-400">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-200">Code</label>
                <input type="text" name="code" value="{{ old('code') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="e.g. ZANACO">
                @error('code')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-white/20">
                Active
            </label>
            <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                Create institution
            </button>
        </form>
    </div>
@endsection
