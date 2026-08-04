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

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search branches…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Province</th>
                            <th scope="col">District</th>
                            <th scope="col">Branch Manager</th>
                            <th scope="col">Admins</th>
                            <th scope="col">Total Disbursed</th>
                            <th scope="col">Monthly</th>
                            <th scope="col">Weekly</th>
                            <th scope="col">Daily</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $branch->name }}</td>
                                <td>{{ $branch->code }}</td>
                                <td>{{ $branch->province->name ?? '—' }}</td>
                                <td>{{ $branch->district->name ?? '—' }}</td>
                                <td>
                                    @if($branch->manager)
                                        <div class="text-sm text-white">{{ $branch->manager->full_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $branch->manager->email }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">Not assigned</span>
                                    @endif
                                </td>
                                <td>
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
                                <td class="text-white">ZMW {{ number_format($stats['total'], 2) }}</td>
                                <td class="text-slate-200">ZMW {{ number_format($stats['monthly'], 2) }}</td>
                                <td class="text-slate-200">ZMW {{ number_format($stats['weekly'], 2) }}</td>
                                <td class="text-slate-200">ZMW {{ number_format($stats['daily'], 2) }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $branch->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.reports.branches', ['branch_id' => $branch->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-cyan-400/50 bg-cyan-500/10 px-2.5 py-1 text-xs font-semibold text-cyan-700 hover:bg-cyan-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            View
                                        </a>
                                        <a href="{{ route('admin.branches.edit', $branch) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="py-8 text-center text-slate-400">No branches found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </div>
@endsection
