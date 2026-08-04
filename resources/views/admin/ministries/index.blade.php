@extends('layouts.admin')

@section('title', 'Ministries | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Ministries',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create Ministry',
                    'href' => route('admin.ministries.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search ministries…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ministries as $ministry)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $ministry->name }}</td>
                                <td>{{ $ministry->code }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $ministry->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $ministry->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.ministries.edit', $ministry) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
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
                                <td colspan="4" class="py-8 text-center text-slate-400">No ministries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </div>
@endsection
