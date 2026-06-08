@extends('layouts.admin')

@section('title', 'Channel · '.$channel->name)

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $channel->name,
            'description' => 'Code: '.$channel->code,
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Channels',
                    'href' => route('admin.channels.index'),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m11 14H4"/></svg>'
                ],
                [
                    'action' => 'edit',
                    'text' => 'Edit Channel',
                    'href' => route('admin.channels.edit', $channel),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.732-6.732a2.121 2.121 0 013 3L12 14l-4 1 1-4z"/></svg>'
                ],
            ]
        ])

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-500/20 via-sky-900/20 to-transparent p-6 shadow-xl lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-cyan-200">Channel Overview</p>
                        <h2 class="text-2xl font-semibold text-white mt-2">{{ $channel->name }}</h2>
                        <p class="text-sm text-slate-300 mt-1 font-mono">{{ $channel->code }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                            {{ $channel->code }}
                        </span>
                        @php
                            $typeBadgeClass = match ($channel->type) {
                                \App\Models\Channel::TYPE_BANK => 'border-blue-400/60 bg-blue-500/20 text-blue-100',
                                \App\Models\Channel::TYPE_CASH => 'border-amber-400/60 bg-amber-500/20 text-amber-100',
                                default => 'border-cyan-400/60 bg-cyan-500/20 text-cyan-100',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $typeBadgeClass }}">
                            {{ $channel->typeLabel() }}
                        </span>
                        <span class="inline-flex items-center rounded-full border {{ $channel->is_active ? 'border-emerald-400/60 bg-emerald-500/20 text-emerald-100' : 'border-rose-400/60 bg-rose-500/20 text-rose-100' }} px-3 py-1 text-xs font-semibold">
                            {{ $channel->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                @if($channel->description)
                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-400 mb-2">Description</p>
                        <p class="text-sm text-white leading-relaxed">{{ $channel->description }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Capabilities</h3>
                </div>
                <div class="space-y-6">
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Disbursement</p>
                        @if($channel->can_disburse)
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-lg font-semibold text-emerald-300">Enabled</p>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Can be used for loan disbursements</p>
                        @else
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <p class="text-lg font-semibold text-slate-400">Disabled</p>
                            </div>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Repayment</p>
                        @if($channel->can_repay)
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-lg font-semibold text-emerald-300">Enabled</p>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Can be used for loan repayments</p>
                            <p class="text-xs mt-1 {{ $channel->is_repayment_integrated ? 'text-cyan-300' : 'text-amber-300' }}">
                                {{ $channel->is_repayment_integrated ? 'Integrated: Sent to automated processing' : 'Manual: Submitted for admin approval' }}
                            </p>
                        @else
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <p class="text-lg font-semibold text-slate-400">Disabled</p>
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm text-white/80 pt-4 border-t border-white/10">
                        <div>
                            <p class="text-xs uppercase text-slate-500 mb-1">Created</p>
                            <p>{{ $channel->created_at->format('d M Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-500 mb-1">Updated</p>
                            <p>{{ $channel->updated_at->format('d M Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
