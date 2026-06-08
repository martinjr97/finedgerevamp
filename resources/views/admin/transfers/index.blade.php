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

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Date</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Transaction #</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">From</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">To</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Amount</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Description</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transfers as $transfer)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 border-r border-white/5">{{ $transfer->transaction_date->format('M d, Y') }}</td>
                                <td class="px-4 py-4 font-mono text-sm border-r border-white/5">{{ $transfer->transaction_number }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $transfer->source->name ?? '—' }}</td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $transfer->destination->name ?? '—' }}</td>
                                <td class="px-4 py-4 font-semibold text-blue-400 border-r border-white/5">{{ number_format($transfer->amount, 2) }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @if($transfer->approval_status)
                                        <span class="text-sm font-medium {{ $transfer->approval_status === 'approved' ? 'text-emerald-400' : ($transfer->approval_status === 'pending' ? 'text-amber-400' : 'text-rose-400') }}">
                                            {{ ucfirst($transfer->approval_status) }}
                                        </span>
                                    @else
                                        <span class="text-sm font-medium text-emerald-400">Approved</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-left border-r border-white/5">{{ $transfer->description }}</td>
                                <td class="px-4 py-4">
                                    @can('transfers.view')
                                    <a href="{{ route('admin.transfers.show', $transfer) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-purple-500/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">No transfers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $transfers->links() }}
            </div>
        </div>
    </div>
@endsection

