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

        <div class="admin-data-table">
            <div class="admin-data-table__scroll">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="font-semibold uppercase text-white/80 text-center">
                            <th>Date</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Recipients</th>
                            <th>Sent</th>
                            <th>Failed</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($communications as $communication)
                            <tr class="text-center">
                                <td>{{ $communication->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $communication->type === 'email' ? 'text-blue-400' : ($communication->type === 'sms' ? 'text-purple-400' : 'text-cyan-400') }}">
                                        {{ strtoupper($communication->type) }}
                                    </span>
                                </td>
                                <td class="text-left">
                                    {{ $communication->is_sensitive ? ($communication->masked_subject ?? '—') : ($communication->subject ?: ($communication->type === 'sms' ? 'SMS notification' : '—')) }}
                                    @if($communication->is_sensitive)
                                        <span class="ml-2 text-sm text-amber-400" title="Sensitive information masked">🔒</span>
                                    @endif
                                </td>
                                <td>{{ $communication->recipients_count }}</td>
                                <td class="text-emerald-400">{{ $communication->sent_count }}</td>
                                <td class="text-rose-400">{{ $communication->failed_count }}</td>
                                <td>
                                    <span class="text-sm font-medium {{ $communication->status === 'completed' ? 'text-emerald-400' : ($communication->status === 'failed' ? 'text-rose-400' : ($communication->status === 'sending' ? 'text-yellow-400' : 'text-slate-400')) }}">
                                        {{ ucfirst($communication->status) }}
                                    </span>
                                </td>
                                <td>{{ $communication->creator->full_name ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('admin.communications.show', $communication) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-8 text-center text-slate-400">No communications found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-table-footer">
                {{ $communications->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection

