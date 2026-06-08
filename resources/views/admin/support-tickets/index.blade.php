@extends('layouts.admin')

@section('title', 'Support Tickets | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Support Tickets',
            'description' => 'Review and manage customer support requests',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Create Ticket',
                    'href' => route('admin.support-tickets.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                ],
            ],
        ])

        <form method="GET" action="{{ route('admin.support-tickets.index') }}" class="flex flex-wrap gap-3 items-end rounded-2xl border border-white/10 bg-white/5 p-4">
            <div>
                <label for="status" class="block text-xs font-medium text-slate-300 mb-1">Status</label>
                <select id="status" name="status" class="rounded-xl border border-white/15 bg-black/30 px-3 py-2 text-sm text-slate-100">
                    <option value="">All</option>
                    @foreach (\App\Models\SupportTicket::statuses() as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="assignment" class="block text-xs font-medium text-slate-300 mb-1">Assignment</label>
                <select id="assignment" name="assignment" class="rounded-xl border border-white/15 bg-black/30 px-3 py-2 text-sm text-slate-100">
                    <option value="">All</option>
                    <option value="me" @selected(request('assignment') === 'me')>Assigned to me</option>
                    <option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned</option>
                </select>
            </div>
            <div>
                <label for="sort" class="block text-xs font-medium text-slate-300 mb-1">Sort</label>
                <select id="sort" name="sort" class="rounded-xl border border-white/15 bg-black/30 px-3 py-2 text-sm text-slate-100">
                    <option value="newest" @selected(request('sort', 'newest') !== 'oldest')>Newest first</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-cyan-500/30 border border-cyan-400/50 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-cyan-500/40">Filter</button>
        </form>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="support-ticket-table min-w-full w-full text-base">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Ticket #</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Submitted By</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Subject</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Assigned</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Open</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Created</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr class="text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 border-r border-white/5 font-semibold text-white">#{{ $ticket->id }}</td>
                                <td class="px-4 py-4 border-r border-white/5 text-left">
                                    <span class="text-base font-medium text-white">{{ $ticket->name }}</span>
                                    @if ($ticket->email)<span class="block text-xs text-slate-400">{{ $ticket->email }}</span>@endif
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 text-left">
                                    <span class="text-base">{{ \Illuminate\Support\Str::limit($ticket->subject, 50) }}</span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 text-sm text-slate-300">
                                    {{ $ticket->assignedStaffName() ?? '—' }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <span class="text-sm font-medium">{{ $ticket->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 text-xs text-slate-400">
                                    {{ $ticket->ageForHumans() }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5 text-xs">
                                    {{ $ticket->created_at?->format('d M Y H:i') }}
                                    @if ($ticket->comments_max_created_at)
                                        <span class="block text-slate-500">Last reply {{ \Carbon\Carbon::parse($ticket->comments_max_created_at)->diffForHumans() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.support-tickets.show', $ticket) }}"
                                       class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-blue-500/40 border-2 border-blue-400/70 px-4 py-2 text-base font-semibold text-blue-100 hover:from-blue-500/60 hover:to-blue-500/60 transition">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No support tickets found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $tickets->links() }}</div>
        </div>
    </div>
@endsection
