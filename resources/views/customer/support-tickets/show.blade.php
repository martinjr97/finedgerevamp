@extends('layouts.customer')

@section('title', 'Support Ticket #' . $ticket->id)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Ticket #{{ $ticket->id }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $ticket->subject }}</p>
            </div>
            <a href="{{ route('customer.support') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">
                ← Back to Support
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-md space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                    {{ $ticket->statusLabel() }}
                </span>
                <span class="text-xs text-gray-500">Submitted {{ $ticket->created_at?->format('d M Y, h:i A') }}</span>
            </div>
            <p class="text-xs text-gray-500">Open for {{ $ticket->ageForHumans() }}</p>
        </div>

        @include('partials.support-ticket-attachments', [
            'ticket' => $ticket,
            'attachments' => $ticket->customerVisibleAttachments,
            'isAdminView' => false,
        ])

        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-md space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Conversation</h2>
            <div class="space-y-3">
                @forelse ($ticket->comments as $comment)
                    @include('partials.support-ticket-comment', ['comment' => $comment, 'isAdminView' => false])
                @empty
                    @if ($ticket->message)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Your message</p>
                            <p class="whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $ticket->message }}</p>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>

        @if ($canComment)
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-md space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add a reply</h2>
                <form method="POST" action="{{ route('customer.support-tickets.comments.store', $ticket) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="comment" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Your message</label>
                        <textarea id="comment" name="comment" rows="4" required
                            class="mt-2 w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none resize-y"
                            placeholder="Type your reply here">{{ old('comment') }}</textarea>
                        @error('comment')<p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg hover:from-blue-600 hover:to-indigo-700 transition">
                        Send Reply
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This ticket is {{ strtolower($ticket->statusLabel()) }} and no longer accepts replies. Contact support if you need further help.
            </div>
        @endif
    </div>
@endsection
