@extends('layouts.admin')

@section('title', 'Channels | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Payment Channels',
            'description' => 'Manage disbursement and repayment channels',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create Channel',
                    'href' => route('admin.channels.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="admin-data-table">
            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search channels…"
                class="min-w-full w-full"
            >
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Type</th>
                            <th scope="col">Description</th>
                            <th scope="col">Disbursement</th>
                            <th scope="col">Repayment</th>
                            <th scope="col">Repayment Mode</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($channels as $channel)
                            <tr class="text-center">
                                <td class="font-medium text-white">{{ $channel->name }}</td>
                                <td>
                                    <span class="text-sm text-cyan-300 font-mono">{{ $channel->code }}</span>
                                </td>
                                <td>
                                    @php
                                        $typeBadgeClass = match ($channel->type) {
                                            \App\Models\Channel::TYPE_BANK => 'border-blue-400/60 bg-blue-500/20 text-blue-100',
                                            \App\Models\Channel::TYPE_CASH => 'border-amber-400/60 bg-amber-500/20 text-amber-100',
                                            default => 'border-cyan-400/60 bg-cyan-500/20 text-cyan-100',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $typeBadgeClass }}">
                                        {{ $channel->typeLabel() }}
                                    </span>
                                </td>
                                <td class="text-sm text-slate-300">
                                    {{ $channel->description ? \Illuminate\Support\Str::limit($channel->description, 50) : '—' }}
                                </td>
                                <td>
                                    <span class="text-sm font-medium {{ $channel->can_disburse ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $channel->can_disburse ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm font-medium {{ $channel->can_repay ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $channel->can_repay ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    @if($channel->can_repay)
                                        <span class="text-sm font-medium {{ $channel->is_repayment_integrated ? 'text-cyan-300' : 'text-amber-300' }}">
                                            {{ $channel->is_repayment_integrated ? 'Integrated' : 'Manual Approval' }}
                                        </span>
                                    @else
                                        <span class="text-sm font-medium text-slate-400">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm font-medium {{ $channel->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $channel->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.channels.show', $channel) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        <a href="{{ route('admin.channels.edit', $channel) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition">
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
                                <td colspan="8" class="py-8 text-center text-slate-400">No channels found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
        </div>
    </div>
@endsection
