@extends('layouts.admin')

@section('title', 'Admins | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Admin Users',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create Admin',
                    'href' => route('admin.users.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('admins.create')
                ],
                [
                    'action' => 'export',
                    'text' => 'Export CSV',
                    'href' => route('admin.users.export'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('admins.view')
                ]
            ]
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search users…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Company</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Roles</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Login</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="text-center">
                                <td class="font-medium text-white">
                                    {{ $user->full_name }}
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->company->name ?? '—' }}</td>
                                <td>{{ $user->branch->name ?? '—' }}</td>
                                <td>
                                    <span class="rounded-full bg-white/5 px-2 py-1 text-sm">
                                        {{ $user->roles->pluck('name')->join(', ') ?: '—' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-sm font-medium {{ $user->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        @if ($user->approval_status === 'pending')
                                            <span class="text-sm font-medium text-amber-400">
                                                Pending Approval
                                            </span>
                                        @elseif ($user->approval_status === 'rejected')
                                            <span class="text-sm font-medium text-rose-400">
                                                Rejected
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-sm text-slate-400">
                                    @if ($user->last_login_at)
                                        {{ $user->last_login_at->format('d M Y H:i') }}
                                        <span class="block text-xs mt-1">IP: {{ $user->last_login_ip ?? 'N/A' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        @can('admins.view')
                                        <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        @endcan
                                        @can('admins.update')
                                        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
        </div>
    </div>
@endsection
