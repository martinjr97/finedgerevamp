@extends('layouts.customer')

@section('title', 'My Support Tickets')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Support Tickets</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">View previous requests and follow up with our team.</p>
            </div>
            <a href="{{ route('customer.support') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 transition hover:bg-gray-50 dark:hover:bg-gray-800">
                ← Back to Support
            </a>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-md space-y-4">
            @if($supportTickets->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/50 p-5 text-sm text-slate-600 dark:text-slate-300">
                    You have not submitted any support tickets yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach($supportTickets as $ticket)
                        @php
                            $statusClasses = [
                                'new' => 'border-blue-200 bg-blue-100 text-blue-700 dark:border-blue-500/40 dark:bg-blue-500/20 dark:text-blue-300',
                                'in_progress' => 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300',
                                'resolved' => 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300',
                                'closed' => 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-500/40 dark:bg-slate-500/20 dark:text-slate-300',
                            ];
                            $statusClass = $statusClasses[$ticket->status] ?? 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-500/40 dark:bg-slate-500/20 dark:text-slate-300';
                        @endphp

                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $ticket->subject }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Ticket #{{ $ticket->id }} • {{ $ticket->created_at?->format('d M Y, h:i A') ?? 'N/A' }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($ticket->message, 110) }}</p>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $ticket->statusLabel() }}
                                    </span>
                                    <a
                                        href="{{ route('customer.support-tickets.show', $ticket) }}"
                                        class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                                    >
                                        View &amp; Reply
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($supportTickets->hasPages())
                    <div class="pt-2">
                        {{ $supportTickets->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
