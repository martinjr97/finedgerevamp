@extends('layouts.admin')

@section('title', $institution->name.' Branches | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $institution->name,
            'description' => 'Branches for customer bank disbursement',
            'buttons' => [
                ['action' => 'secondary', 'text' => 'Edit institution', 'href' => route('admin.financial-institutions.edit', $institution)],
                ['action' => 'secondary', 'text' => 'All institutions', 'href' => route('admin.financial-institutions.index')],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-400/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{{ session('error') }}</div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-sm font-semibold uppercase tracking-wide text-white/80 text-center border-b border-white/20">
                            <th class="px-4 py-3 border-r border-white/10">Branch name</th>
                            <th class="px-4 py-3 border-r border-white/10">Code</th>
                            <th class="px-4 py-3 border-r border-white/10">Sort code</th>
                            <th class="px-4 py-3 border-r border-white/10">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($institution->branches as $branch)
                            <tr class="border-t border-white/20 text-center">
                                <td class="px-4 py-3 text-white border-r border-white/5">{{ $branch->name }}</td>
                                <td class="px-4 py-3 border-r border-white/5 font-mono text-sm">{{ $branch->code ?? '—' }}</td>
                                <td class="px-4 py-3 border-r border-white/5">{{ $branch->sort_code ?? '—' }}</td>
                                <td class="px-4 py-3 border-r border-white/5">
                                    <span class="{{ $branch->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @can('financial-institutions.update')
                                        <a href="{{ route('admin.financial-institutions.branches.edit', [$institution, $branch]) }}"
                                           class="inline-flex items-center gap-1 rounded-xl border border-purple-400/60 bg-purple-500/20 px-3 py-1 text-sm font-semibold text-purple-200 hover:text-white transition">
                                            Edit
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-slate-400">No branches yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @can('financial-institutions.update')
            <form method="POST" action="{{ route('admin.financial-institutions.branches.store', $institution) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-2xl">
                @csrf
                <h2 class="text-lg font-semibold text-white">Add branch</h2>
                <div>
                    <label class="text-sm font-medium text-slate-200">Branch name <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-200">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-200">Sort code</label>
                        <input type="text" name="sort_code" value="{{ old('sort_code') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-white/20">
                    Active
                </label>
                <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Add branch
                </button>
            </form>
        @endcan
    </div>
@endsection
