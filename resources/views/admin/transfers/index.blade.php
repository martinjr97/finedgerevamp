@extends('layouts.admin')

@section('title', 'Transfers | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Transfers',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'New Transfer',
                    'href' => route('admin.transfers.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('transfers.create')
                ]
            ]
        ])

        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Date</th>
                            <th scope="col">Transaction #</th>
                            <th scope="col">From</th>
                            <th scope="col">To</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Description</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            <tr class="text-center">
                                <td>{{ $transfer->transaction_date->format('M d, Y') }}</td>
                                <td class="font-mono text-sm">{{ $transfer->transaction_number }}</td>
                                <td>{{ $transfer->source->name ?? '—' }}</td>
                                <td>{{ $transfer->destination->name ?? '—' }}</td>
                                <td class="font-semibold text-blue-400">{{ number_format($transfer->amount, 2) }}</td>
                                <td>
                                    @if($transfer->approval_status)
                                        <span class="text-sm font-medium {{ $transfer->approval_status === 'approved' ? 'text-emerald-400' : ($transfer->approval_status === 'pending' ? 'text-amber-400' : 'text-rose-400') }}">
                                            {{ ucfirst($transfer->approval_status) }}
                                        </span>
                                    @else
                                        <span class="text-sm font-medium text-emerald-400">Approved</span>
                                    @endif
                                </td>
                                <td class="text-left">{{ $transfer->description }}</td>
                                <td>
                                    @can('transfers.view')
                                    <a href="{{ route('admin.transfers.show', $transfer) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No transfers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-table-footer">
                {{ $transfers->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection

