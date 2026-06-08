@extends('layouts.admin')

@section('title', 'Branches | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Branches',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create Branch',
                    'href' => route('admin.branches.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Code</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Province</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">District</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Branch Manager</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Admins</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Total Disbursed</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Monthly</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Weekly</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Daily</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">{{ $branch->name }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $branch->code }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $branch->province->name ?? '—' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $branch->district->name ?? '—' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @if($branch->manager)
                                        <div class="text-sm text-white">{{ $branch->manager->full_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $branch->manager->email }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">Not assigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="text-sm text-white font-semibold">
                                        {{ $branch->admins->count() }} staff
                                    </div>
                                    @if($branch->admins->isNotEmpty())
                                        <div class="text-xs text-slate-400 mt-1">
                                            {{ $branch->admins->pluck('full_name')->take(3)->join(', ') }}
                                            @if($branch->admins->count() > 3)
                                                +{{ $branch->admins->count() - 3 }} more
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500">None linked</span>
                                    @endif
                                </td>
                                @php
                                    $stats = $branchStats[$branch->id] ?? ['total' => 0, 'monthly' => 0, 'weekly' => 0, 'daily' => 0];
                                @endphp
                                <td class="px-4 py-4 text-white border-r border-white/5">ZMW {{ number_format($stats['total'], 2) }}</td>
                                <td class="px-4 py-4 text-slate-200 border-r border-white/5">ZMW {{ number_format($stats['monthly'], 2) }}</td>
                                <td class="px-4 py-4 text-slate-200 border-r border-white/5">ZMW {{ number_format($stats['weekly'], 2) }}</td>
                                <td class="px-4 py-4 text-slate-200 border-r border-white/5">ZMW {{ number_format($stats['daily'], 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $branch->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.reports.branches', ['branch_id' => $branch->id]) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-cyan-400/60 bg-cyan-500/20 px-4 py-2 text-base font-semibold text-cyan-200 hover:bg-cyan-500/30 hover:border-cyan-300 hover:text-white transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            View
                                        </a>
                                        <a href="{{ route('admin.branches.edit', $branch) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-purple-500/40 to-indigo-500/40 border-2 border-purple-400/70 px-4 py-2 text-base font-semibold text-purple-200 hover:from-purple-500/60 hover:to-indigo-500/60 hover:border-purple-400 hover:text-white transition shadow-md shadow-purple-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-slate-400">No branches found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
