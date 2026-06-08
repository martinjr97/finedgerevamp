@extends('layouts.admin')

@section('title', 'Edit Branch | '.$institution->name)

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Branch',
            'description' => $institution->name.' · '.$branch->name,
            'buttons' => [
                ['action' => 'secondary', 'text' => 'Back to branches', 'href' => route('admin.financial-institutions.branches', $institution)],
            ],
        ])

        @if(session('error'))
            <div class="rounded-2xl border border-rose-400/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.financial-institutions.branches.update', [$institution, $branch]) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm font-medium text-slate-200">Branch name <span class="text-rose-400">*</span></label>
                <input type="text" name="name" value="{{ old('name', $branch->name) }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-200">Code</label>
                    <input type="text" name="code" value="{{ old('code', $branch->code) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('code')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-200">Sort code</label>
                    <input type="text" name="sort_code" value="{{ old('sort_code', $branch->sort_code) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('sort_code')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $branch->is_active) ? 'checked' : '' }} class="rounded border-white/20">
                Active
            </label>
            <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                Save branch
            </button>
        </form>
    </div>
@endsection
