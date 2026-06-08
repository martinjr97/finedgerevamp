@extends('layouts.admin')

@section('title', 'Companies | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Companies',
            'buttons' => [
                [
                    'action' => 'register',
                    'text' => 'Register Company',
                    'href' => route('admin.companies.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('companies.create')
                ],
                [
                    'action' => 'export',
                    'text' => 'Export to Excel',
                    'href' => route('admin.companies.export'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                    'can' => auth('admin')->user()?->can('companies.export')
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Code</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Type</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Sector</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Admins</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Customers</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($companies as $company)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $company->name }}
                                    @if ($company->is_primary)
                                        <span class="ml-2 text-sm font-medium text-amber-400">(Primary)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $company->code }}</td>
                                <td class="px-4 py-4 capitalize border-r border-white/5">{{ $company->type }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="rounded-full bg-white/5 px-2 py-1 text-sm inline-block">
                                        {{ $company->sector->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="flex flex-col gap-1 items-center">
                                        <span class="text-sm font-medium {{ $company->status === 'active' ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ ucfirst($company->status) }}
                                        </span>
                                        @if ($company->approval_status === 'pending')
                                            <span class="text-sm font-medium text-amber-400">
                                                Pending Approval
                                            </span>
                                        @elseif ($company->approval_status === 'rejected')
                                            <span class="text-sm font-medium text-rose-400">
                                                Rejected
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $company->admins_count }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $company->customers_count }}</td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        @can('companies.view')
                                        <a href="{{ route('admin.companies.show', $company) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        @endcan
                                        @can('companies.update')
                                        <a href="{{ route('admin.companies.edit', $company) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    </div>
@endsection
