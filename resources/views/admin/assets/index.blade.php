@extends('layouts.admin')

@section('title', 'Physical Assets | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Physical Assets',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Add Asset',
                    'href' => route('admin.assets.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('assets.create')
                ]
            ]
        ])

        <div class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 px-4 py-3 text-sm text-cyan-100">
            Active assets total value: <span class="font-semibold text-white">ZMW {{ number_format($totalValue, 2) }}</span>
        </div>

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search physical assets…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr class="font-semibold uppercase text-white/80 text-center">
                        <th scope="col">Type</th>
                        <th scope="col">Name</th>
                        <th scope="col">Value</th>
                        <th scope="col">Acquired</th>
                        <th scope="col">Status</th>
                        <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        <tr class="text-center">
                            <td class="font-medium text-white">{{ $asset->asset_type }}</td>
                            <td class="text-left">{{ $asset->name }}</td>
                            <td class="font-semibold">{{ number_format($asset->value, 2) }}</td>
                            <td>{{ $asset->acquisition_date ? $asset->acquisition_date->format('M d, Y') : '—' }}</td>
                            <td>
                                <span class="text-sm font-medium {{ $asset->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $asset->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="inline-flex items-center gap-3">
                                    @can('assets.view')
                                    <a href="{{ route('admin.assets.show', $asset) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                        View
                                    </a>
                                    @endcan
                                    @can('assets.update')
                                    <a href="{{ route('admin.assets.edit', $asset) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
                                        Edit
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No physical assets found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
