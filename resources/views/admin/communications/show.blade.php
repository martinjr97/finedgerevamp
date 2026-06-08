@extends('layouts.admin')

@section('title', 'Communication Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Communication Details',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Send New',
                    'href' => route('admin.communications.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
                ]
            ]
        ])

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Communication Details -->
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h3 class="text-lg font-semibold text-white mb-4">Communication Information</h3>
                
                <div>
                    <label class="text-sm text-slate-400">Type</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $communication->type === 'email' ? 'bg-blue-500/20 text-blue-300' : ($communication->type === 'sms' ? 'bg-purple-500/20 text-purple-300' : 'bg-cyan-500/20 text-cyan-300') }}">
                            {{ strtoupper($communication->type) }}
                        </span>
                    </p>
                </div>

                @if($communication->subject)
                <div>
                    <label class="text-sm text-slate-400">Subject</label>
                    <p class="text-white font-medium">{{ $communication->is_sensitive ? $communication->masked_subject : $communication->subject }}</p>
                    @if($communication->is_sensitive)
                        <p class="text-xs text-amber-400 mt-1">⚠️ Sensitive information has been masked</p>
                    @endif
                </div>
                @endif

                <div>
                    <label class="text-sm text-slate-400">Message</label>
                    <div class="mt-2 p-4 rounded-2xl bg-white/5 border border-white/10 text-white whitespace-pre-wrap">{{ $communication->is_sensitive ? $communication->masked_message : $communication->message }}</div>
                    @if($communication->is_sensitive)
                        <p class="text-xs text-amber-400 mt-1">⚠️ Sensitive information (OTP, PIN) has been masked for security</p>
                    @endif
                </div>

                <div>
                    <label class="text-sm text-slate-400">Status</label>
                    <p>
                        <span class="rounded-full px-2 py-1 text-xs {{ $communication->status === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : ($communication->status === 'failed' ? 'bg-rose-500/20 text-rose-300' : ($communication->status === 'sending' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-slate-500/20 text-slate-300')) }}">
                            {{ ucfirst($communication->status) }}
                        </span>
                    </p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Created By</label>
                    <p class="text-white">{{ $communication->creator->full_name ?? '—' }}</p>
                </div>

                <div>
                    <label class="text-sm text-slate-400">Sent At</label>
                    <p class="text-white">{{ $communication->sent_at ? $communication->sent_at->format('M d, Y H:i:s') : '—' }}</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h3 class="text-lg font-semibold text-white mb-4">Statistics</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                        <p class="text-sm text-slate-400 mb-1">Total Recipients</p>
                        <p class="text-2xl font-bold text-white">{{ $communication->recipients_count }}</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30">
                        <p class="text-sm text-emerald-300 mb-1">Successfully Sent</p>
                        <p class="text-2xl font-bold text-emerald-400">{{ $communication->sent_count }}</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30">
                        <p class="text-sm text-rose-300 mb-1">Failed</p>
                        <p class="text-2xl font-bold text-rose-400">{{ $communication->failed_count }}</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                        <p class="text-sm text-slate-400 mb-1">Success Rate</p>
                        <p class="text-2xl font-bold text-white">
                            {{ $communication->recipients_count > 0 ? number_format(($communication->sent_count / $communication->recipients_count) * 100, 1) : 0 }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Applied -->
        @if(!empty($communication->filters))
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h3 class="text-lg font-semibold text-white mb-4">Filters Applied</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if(!empty($communication->filters['product_id']))
                    <div>
                        <label class="text-sm text-slate-400">Product Type</label>
                        <p class="text-white">{{ \App\Models\LoanProduct::find($communication->filters['product_id'])->name ?? '—' }}</p>
                    </div>
                @endif
                @if(!empty($communication->filters['province_id']))
                    <div>
                        <label class="text-sm text-slate-400">Province</label>
                        <p class="text-white">{{ \App\Models\Province::find($communication->filters['province_id'])->name ?? '—' }}</p>
                    </div>
                @endif
                @if(!empty($communication->filters['age_group']))
                    <div>
                        <label class="text-sm text-slate-400">Age Group</label>
                        <p class="text-white">{{ $communication->filters['age_group'] }} years</p>
                    </div>
                @endif
                @if(!empty($communication->filters['has_active_loans']))
                    <div>
                        <label class="text-sm text-slate-400">Loan Status</label>
                        <p class="text-white">{{ $communication->filters['has_active_loans'] === 'with' ? 'With Active Loans' : 'Without Active Loans' }}</p>
                    </div>
                @endif
                @if(!empty($communication->filters['gender']))
                    <div>
                        <label class="text-sm text-slate-400">Gender</label>
                        <p class="text-white capitalize">{{ $communication->filters['gender'] }}</p>
                    </div>
                @endif
                @if(empty(array_filter($communication->filters)))
                    <p class="text-slate-400">No filters applied (sent to all active customers)</p>
                @endif
            </div>
        </div>
        @endif

        <!-- Recipients -->
        @if($recipients && $recipients->count() > 0)
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h3 class="text-lg font-semibold text-white mb-4">Recipients ({{ $recipients->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-sm text-slate-300">
                    <thead>
                        <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                            <th class="px-4 py-4 text-base text-white">Name</th>
                            <th class="px-4 py-4 text-base text-white">Email</th>
                            <th class="px-4 py-4 text-base text-white">Phone</th>
                            <th class="px-4 py-4 text-base text-white">Status</th>
                            <th class="px-4 py-4 text-base text-white">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recipients as $recipient)
                            <tr class="border-b border-white/5 text-center">
                                <td class="px-4 py-3 font-medium text-white">{{ $recipient->full_name }}</td>
                                <td class="px-4 py-3 text-white">{{ $recipient->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-white">{{ $recipient->phone ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs {{ $recipient->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($recipient->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                        {{ ucfirst($recipient->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.customers.show', $recipient) }}" class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 transition">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h3 class="text-lg font-semibold text-white mb-4">Recipients</h3>
            @if(isset($communication->metadata['recipient']) && $communication->metadata['recipient'])
                @php
                    $recipient = $communication->metadata['recipient'];
                @endphp
                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-400">Recipient Type</label>
                        <p class="text-white">{{ $recipient['type'] ? class_basename($recipient['type']) : 'System' }}</p>
                    </div>
                    @if($recipient['name'])
                        <div>
                            <label class="text-sm text-slate-400">Name</label>
                            <p class="text-white">{{ $recipient['name'] }}</p>
                        </div>
                    @endif
                    @if($recipient['email'])
                        <div>
                            <label class="text-sm text-slate-400">Email</label>
                            <p class="text-white">{{ $recipient['email'] }}</p>
                        </div>
                    @endif
                    @if($recipient['phone'])
                        <div>
                            <label class="text-sm text-slate-400">Phone</label>
                            <p class="text-white font-mono">{{ $recipient['phone'] }}</p>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-slate-400 text-center py-4">No recipient information available.</p>
            @endif
        </div>
        @endif

        @if($communication->error_message)
        <div class="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-6 shadow-lg">
            <h3 class="text-lg font-semibold text-rose-300 mb-2">Error Messages</h3>
            <p class="text-sm text-rose-200 whitespace-pre-wrap">{{ $communication->error_message }}</p>
        </div>
        @endif
    </div>
@endsection

