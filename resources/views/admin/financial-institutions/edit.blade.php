@extends('layouts.admin')

@section('title', 'Edit '.$institution->name.' | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Financial Institution',
            'description' => $institution->name,
            'buttons' => [
                ['action' => 'secondary', 'text' => 'Branches', 'href' => route('admin.financial-institutions.branches', $institution)],
                ['action' => 'secondary', 'text' => 'Back', 'href' => route('admin.financial-institutions.index')],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.financial-institutions.update', $institution) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6 max-w-2xl">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm font-medium text-slate-200">Name <span class="text-rose-400">*</span></label>
                <input type="text" name="name" value="{{ old('name', $institution->name) }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-200">Code</label>
                <input type="text" name="code" value="{{ old('code', $institution->code) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('code')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $institution->is_active) ? 'checked' : '' }} class="rounded border-white/20">
                Active
            </label>
            <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                Save changes
            </button>
        </form>
    </div>
@endsection
