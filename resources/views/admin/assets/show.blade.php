@extends('layouts.admin')

@section('title', 'Physical Asset | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $asset->name,
            'buttons' => [
                [
                    'action' => 'back',
                    'text' => 'Back to Assets',
                    'href' => route('admin.assets.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
                ],
                [
                    'text' => 'Edit',
                    'href' => route('admin.assets.edit', $asset),
                    'can' => auth('admin')->user()?->can('assets.update'),
                    'class' => 'inline-flex items-center gap-2 rounded-xl border border-purple-400/40 bg-purple-500/10 px-3 py-2 text-sm font-semibold text-purple-100 hover:bg-purple-500/20 transition',
                ],
                [
                    'text' => 'Transfer',
                    'href' => '#',
                    'can' => auth('admin')->user()?->can('assets.update'),
                    'class' => 'inline-flex items-center gap-2 rounded-xl border border-cyan-400/40 bg-cyan-500/10 px-3 py-2 text-sm font-semibold text-cyan-100 hover:bg-cyan-500/20 transition js-open-asset-transfer-modal',
                    'attributes' => [
                        'data-asset-id' => $asset->id,
                    ],
                ],
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-slate-400">Asset Type</p>
                    <p class="text-white font-semibold mt-1">{{ $asset->asset_type }}</p>
                </div>
                <div>
                    <p class="text-slate-400">Value</p>
                    <p class="text-white font-semibold mt-1">ZMW {{ number_format($asset->value, 2) }}</p>
                </div>
                <div>
                    <p class="text-slate-400">Acquisition Date</p>
                    <p class="text-white font-semibold mt-1">{{ $asset->acquisition_date ? $asset->acquisition_date->format('d M Y') : '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400">Status</p>
                    <p class="font-semibold mt-1 {{ $asset->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $asset->is_active ? 'Active' : 'Inactive' }}
                    </p>
                </div>
                <div>
                    <p class="text-slate-400">Asset Owner</p>
                    <p class="text-white font-semibold mt-1">{{ $asset->employee?->full_name ?? '—' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-slate-400">Description</p>
                    <p class="text-white mt-1">{{ $asset->description ?: '—' }}</p>
                </div>
                @if ($asset->image_path)
                    <div class="md:col-span-2">
                        <p class="text-slate-400 mb-2">Image</p>
                        <img src="{{ asset('storage/'.$asset->image_path) }}" alt="{{ $asset->name }}" class="h-40 w-40 rounded-xl object-cover border border-white/10">
                    </div>
                @endif
                <div>
                    <p class="text-slate-400">Created by</p>
                    <p class="text-white mt-1">{{ $asset->createdBy?->full_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400">Updated by</p>
                    <p class="text-white mt-1">{{ $asset->updatedBy?->full_name ?? '—' }}</p>
                </div>
            </div>

            @can('assets.delete')
                <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}" class="pt-4 border-t border-white/10"
                      onsubmit="return confirm('Remove this physical asset?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-200 hover:bg-rose-500/20 transition">
                        Delete Asset
                    </button>
                </form>
            @endcan
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Transfer History</h2>
                <p class="text-sm text-slate-400 mt-1">Complete trail of ownership changes for this asset.</p>
            </div>

            <div class="admin-data-table">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Date</th>
                            <th scope="col">From</th>
                            <th scope="col">To</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Transferred By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($asset->transfers as $transfer)
                            <tr class="text-center">
                                <td>{{ $transfer->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $transfer->fromEmployee?->full_name ?? 'Unassigned' }}</td>
                                <td class="font-medium text-white">{{ $transfer->toEmployee?->full_name ?? 'Unassigned' }}</td>
                                <td class="text-left">{{ $transfer->reason ?: '—' }}</td>
                                <td>{{ $transfer->transferredBy?->full_name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">No transfers recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('assets.update')
        @include('admin.assets.partials.transfer-modal', [
            'employees' => $employees,
            'assets' => collect([$asset]),
        ])
    @endcan
@endsection
