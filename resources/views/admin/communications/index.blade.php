@extends('layouts.admin')

@section('title', 'Communications | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Communications',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Send Communication',
                    'href' => route('admin.communications.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                    'can' => auth('admin')->user()?->can('communications.create')
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Date</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Type</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Subject</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Recipients</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Sent</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Failed</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Created By</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($communications as $communication)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 border-r border-white/5">{{ $communication->created_at->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $communication->type === 'email' ? 'text-blue-400' : ($communication->type === 'sms' ? 'text-purple-400' : 'text-cyan-400') }}">
                                        {{ strtoupper($communication->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-left border-r border-white/5">
                                    {{ $communication->is_sensitive ? ($communication->masked_subject ?? '—') : ($communication->subject ?? '—') }}
                                    @if($communication->is_sensitive)
                                        <span class="ml-2 text-sm text-amber-400" title="Sensitive information masked">🔒</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $communication->recipients_count }}</td>
                                <td class="px-4 py-4 text-emerald-400 border-r border-white/5">{{ $communication->sent_count }}</td>
                                <td class="px-4 py-4 text-rose-400 border-r border-white/5">{{ $communication->failed_count }}</td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium {{ $communication->status === 'completed' ? 'text-emerald-400' : ($communication->status === 'failed' ? 'text-rose-400' : ($communication->status === 'sending' ? 'text-yellow-400' : 'text-slate-400')) }}">
                                        {{ ucfirst($communication->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">{{ $communication->creator->full_name ?? '—' }}</td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.communications.show', $communication) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-600/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-200 hover:from-blue-500/60 hover:to-blue-600/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-400">No communications found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $communications->links() }}
            </div>
        </div>
    </div>
@endsection

