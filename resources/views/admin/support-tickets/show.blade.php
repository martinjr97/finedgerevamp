@extends('layouts.admin')

@section('title', 'Support Ticket #' . $ticket->id . ' | ' . config('app.system_name'))

@section('content')
    @php
        $openAssignModal = $canAssign && $errors->hasAny(['assigned_to_id', 'note']);
        $openCommentModal = $canComment && $errors->has('comment');
        $openStatusModal = $canUpdateStatus && $errors->hasAny(['status', 'resolution_note']);
    @endphp

    <div
        class="space-y-8"
        x-data="{
            assignModalOpen: @js($openAssignModal),
            commentModalOpen: @js($openCommentModal),
            statusModalOpen: @js($openStatusModal),
            status: @js(old('status', $ticket->status)),
        }"
        @keydown.escape.window="assignModalOpen = false; commentModalOpen = false; statusModalOpen = false"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Support Management</p>
                <h1 class="text-3xl font-bold text-white">Ticket #{{ $ticket->id }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if ($canComment)
                    <button
                        type="button"
                        @click="commentModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500/30 to-blue-600/30 border border-cyan-400/50 px-4 py-3 text-sm font-semibold text-cyan-100 hover:from-cyan-500/40 hover:to-blue-600/40 transition"
                    >
                        Add Reply / Note
                    </button>
                @endif
                @if ($canAssign)
                    <button
                        type="button"
                        @click="assignModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-white/10 transition"
                    >
                        Assign Staff
                    </button>
                @endif
                @if ($canUpdateStatus)
                    <button
                        type="button"
                        @click="statusModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-2xl border border-blue-400/50 bg-blue-500/20 px-4 py-3 text-sm font-semibold text-blue-100 hover:bg-blue-500/30 transition"
                    >
                        Update Status
                    </button>
                @endif
                <a href="{{ route('admin.support-tickets.index') }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-sm font-medium text-slate-200 hover:bg-white/10 transition">
                    Back to Tickets
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-slate-300">Subject</p>
                            <p class="text-xl font-semibold text-white">{{ $ticket->subject }}</p>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold {{ $ticket->statusColorClass() }}">
                            <span class="w-2 h-2 rounded-full bg-current"></span>
                            <span>{{ $ticket->statusLabel() }}</span>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 text-xs text-slate-300">
                        <div>
                            <p class="uppercase tracking-[0.2em] text-slate-400 mb-1">Submitted By</p>
                            <p class="text-sm font-medium text-white">{{ $ticket->name }}</p>
                            @if ($ticket->email)<p>{{ $ticket->email }}</p>@endif
                            @if ($ticket->phone)<p>Phone: {{ $ticket->phone }}</p>@endif
                            @if ($ticket->customer)
                                <a href="{{ route('admin.customers.show', $ticket->customer) }}" class="mt-2 inline-block text-cyan-300 hover:underline text-xs font-semibold">
                                    {{ $ticket->customer->full_name }} (Customer #{{ $ticket->customer->id }})
                                </a>
                            @endif
                        </div>
                        <div>
                            <p class="uppercase tracking-[0.2em] text-slate-400 mb-1">SLA / Timing</p>
                            <p>Open for: <span class="font-medium text-white">{{ $ticket->ageForHumans() }}</span></p>
                            <p>Created: <span class="font-medium text-white">{{ $ticket->created_at?->format('d M Y H:i') }}</span></p>
                            @if ($ticket->last_assigned_at)
                                <p>Last assigned: <span class="font-medium text-white">{{ $ticket->timeSinceLastAssignmentForHumans() }} ago</span></p>
                            @endif
                            @if ($ticket->resolved_at)
                                <p>Resolved: <span class="font-medium text-white">{{ $ticket->resolved_at->format('d M Y H:i') }}</span></p>
                            @endif
                            @if ($ticket->closed_at)
                                <p>Closed: <span class="font-medium text-white">{{ $ticket->closed_at->format('d M Y H:i') }}</span></p>
                            @endif
                        </div>
                    </div>
                </div>

                @include('partials.support-ticket-attachments', [
                    'ticket' => $ticket,
                    'attachments' => $ticket->attachments,
                    'isAdminView' => true,
                ])

                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h2 class="text-lg font-semibold text-white">Conversation Timeline</h2>
                    <div class="space-y-3 max-h-[640px] overflow-y-auto pr-1">
                        @forelse ($ticket->comments as $comment)
                            @include('partials.support-ticket-comment', ['comment' => $comment, 'isAdminView' => true])
                        @empty
                            <p class="text-sm text-slate-400">No comments yet.</p>
                            @if ($ticket->message)
                                <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
                                    <p class="text-xs uppercase text-slate-400 mb-2">Original message</p>
                                    <p class="whitespace-pre-line text-sm text-slate-200">{{ $ticket->message }}</p>
                                </div>
                            @endif
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg space-y-4">
                    <h2 class="text-lg font-semibold text-white">Ticket Actions</h2>
                    <p class="text-sm text-slate-400">Use the buttons above or below to assign staff, reply, or change status.</p>
                    <div class="flex flex-col gap-2">
                        @if ($canComment)
                            <button type="button" @click="commentModalOpen = true"
                                class="w-full rounded-2xl bg-gradient-to-r from-cyan-500/25 to-blue-600/25 border border-cyan-400/40 px-4 py-2.5 text-sm font-semibold text-cyan-100 hover:from-cyan-500/35 hover:to-blue-600/35 transition text-left">
                                Add reply or internal note
                            </button>
                        @endif
                        @if ($canAssign)
                            <button type="button" @click="assignModalOpen = true"
                                class="w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/10 transition text-left">
                                Assign to staff
                            </button>
                        @endif
                        @if ($canUpdateStatus)
                            <button type="button" @click="statusModalOpen = true"
                                class="w-full rounded-2xl border border-blue-400/40 bg-blue-500/15 px-4 py-2.5 text-sm font-semibold text-blue-100 hover:bg-blue-500/25 transition text-left">
                                Update ticket status
                            </button>
                        @endif
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg space-y-3">
                    <h2 class="text-lg font-semibold text-white">Assignment</h2>
                    <p class="text-sm text-slate-300">
                        Assigned to:
                        <span class="font-semibold text-white">{{ $ticket->assignedStaffName() ?? 'Unassigned' }}</span>
                    </p>
                    @if ($ticket->assignedBy)
                        <p class="text-xs text-slate-400">Last assigned by {{ $ticket->assignedBy->full_name }}</p>
                    @endif
                    @if ($ticket->assignments->isNotEmpty())
                        <div class="pt-3 border-t border-white/10 space-y-2">
                            <p class="text-xs uppercase tracking-wide text-slate-400">Recent history</p>
                            @foreach ($ticket->assignments->take(5) as $assignment)
                                <p class="text-xs text-slate-300">
                                    {{ $assignment->assigned_at?->format('d M Y H:i') }} —
                                    {{ $assignment->assignedTo?->full_name ?? 'Unassigned' }}
                                    @if ($assignment->assignedBy) (by {{ $assignment->assignedBy->full_name }}) @endif
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($ticket->resolution_note)
                    <div class="rounded-3xl border border-emerald-400/30 bg-emerald-500/10 p-5 shadow-lg">
                        <p class="text-xs uppercase tracking-wide text-emerald-300 mb-2">Resolution summary</p>
                        <p class="whitespace-pre-line text-sm text-emerald-100">{{ $ticket->resolution_note }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Assign modal --}}
        @if ($canAssign)
            <div
                x-show="assignModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="assign-modal-title"
            >
                <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.away="assignModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="assign-modal-title" class="text-xl font-semibold text-white">Assign Ticket</h2>
                        <button type="button" @click="assignModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>
                    <p class="text-sm text-slate-400 mb-5">Assign this ticket to a staff member or leave unassigned.</p>

                    <form method="POST" action="{{ route('admin.support-tickets.assign', $ticket) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="assigned_to_id" class="block text-sm font-medium text-slate-200">Staff member</label>
                            <select id="assigned_to_id" name="assigned_to_id"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:ring-cyan-400/40 focus:outline-none">
                                <option value="">— Unassigned —</option>
                                @foreach ($staffMembers as $staff)
                                    <option value="{{ $staff->id }}" @selected((int) old('assigned_to_id', $ticket->assigned_to_id) === (int) $staff->id)>
                                        {{ $staff->full_name }} ({{ $staff->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_to_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="note" class="block text-sm font-medium text-slate-200">Assignment note (optional)</label>
                            <textarea id="note" name="note" rows="3"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none resize-y"
                                placeholder="Reason for assignment">{{ old('note') }}</textarea>
                            @error('note')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-cyan-400/50 bg-cyan-500/25 px-4 py-2.5 text-sm font-semibold text-cyan-100 hover:bg-cyan-500/35 transition">
                                Save Assignment
                            </button>
                            <button type="button" @click="assignModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Comment modal --}}
        @if ($canComment)
            <div
                x-show="commentModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="comment-modal-title"
            >
                <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.away="commentModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="comment-modal-title" class="text-xl font-semibold text-white">Add Reply or Note</h2>
                        <button type="button" @click="commentModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>
                    <p class="text-sm text-slate-400 mb-5">Post a customer-visible reply or an internal note for your team only.</p>

                    <form method="POST" action="{{ route('admin.support-tickets.comments.store', $ticket) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="comment" class="block text-sm font-medium text-slate-200">Message</label>
                            <textarea id="comment" name="comment" rows="5" required
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-3 text-sm text-slate-100 focus:border-cyan-400 focus:ring-cyan-400/40 focus:outline-none resize-y"
                                placeholder="Write your message">{{ old('comment') }}</textarea>
                            @error('comment')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-start gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="is_internal" value="1" class="mt-1 rounded border-white/20 bg-black/30 text-amber-500 focus:ring-amber-500/40" @checked(old('is_internal'))>
                            <span>Internal note only — not visible to customer</span>
                        </label>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:from-cyan-600 hover:to-blue-700 transition">
                                Post Comment
                            </button>
                            <button type="button" @click="commentModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Status modal --}}
        @if ($canUpdateStatus)
            <div
                x-show="statusModalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="status-modal-title"
            >
                <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl max-h-[90vh] overflow-y-auto" @click.away="statusModalOpen = false">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="status-modal-title" class="text-xl font-semibold text-white">Update Status</h2>
                        <button type="button" @click="statusModalOpen = false" class="rounded-lg border border-white/15 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10 transition">Close</button>
                    </div>
                    <p class="text-sm text-slate-400 mb-5">Change the ticket status. Resolution details are required when marking as resolved.</p>

                    <form method="POST" action="{{ route('admin.support-tickets.status.update', $ticket) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="status" class="block text-sm font-medium text-slate-200">Status</label>
                            <select id="status" name="status" x-model="status"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none">
                                @foreach ($statuses as $statusValue)
                                    <option value="{{ $statusValue }}">{{ ucfirst(str_replace('_', ' ', $statusValue)) }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="status === '{{ \App\Models\SupportTicket::STATUS_RESOLVED }}'" x-cloak>
                            <label for="resolution_note" class="block text-sm font-medium text-slate-200">Resolution details</label>
                            <textarea id="resolution_note" name="resolution_note" rows="3"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-3 text-sm text-slate-100 focus:border-blue-500 focus:outline-none resize-y"
                                x-bind:required="status === '{{ \App\Models\SupportTicket::STATUS_RESOLVED }}'"
                                placeholder="Describe how this was resolved"
                            >{{ old('resolution_note', $ticket->resolution_note) }}</textarea>
                            @error('resolution_note')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="status_comment" class="block text-sm font-medium text-slate-200">Optional comment (visible to customer)</label>
                            <textarea id="status_comment" name="comment" rows="2"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2 text-sm text-slate-100 focus:border-blue-500 focus:outline-none resize-y"
                                placeholder="Optional message for the customer">{{ old('comment') }}</textarea>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:from-blue-600 hover:to-blue-700 transition">
                                Save Status
                            </button>
                            <button type="button" @click="statusModalOpen = false" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
