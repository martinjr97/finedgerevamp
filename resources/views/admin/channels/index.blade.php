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

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Name</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Code</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Type</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Description</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Disbursement</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Repayment</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Repayment Mode</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($channels as $channel)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">{{ $channel->name }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm text-cyan-300 font-mono">{{ $channel->code }}</span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
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
                                <td class="px-4 py-4 text-sm text-slate-300 border-r border-white/5">
                                    {{ $channel->description ? \Illuminate\Support\Str::limit($channel->description, 50) : '—' }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $channel->can_disburse ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $channel->can_disburse ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $channel->can_repay ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $channel->can_repay ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @if($channel->can_repay)
                                        <span class="text-sm font-medium {{ $channel->is_repayment_integrated ? 'text-cyan-300' : 'text-amber-300' }}">
                                            {{ $channel->is_repayment_integrated ? 'Integrated' : 'Manual Approval' }}
                                        </span>
                                    @else
                                        <span class="text-sm font-medium text-slate-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $channel->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                        {{ $channel->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.channels.show', $channel) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-purple-500/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        <a href="{{ route('admin.channels.edit', $channel) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-purple-500/40 to-indigo-500/40 border-2 border-purple-400/70 px-4 py-2 text-base font-semibold text-purple-200 hover:from-purple-500/60 hover:to-indigo-500/60 hover:border-purple-400 hover:text-white transition shadow-md shadow-purple-500/20">
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
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No channels found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
