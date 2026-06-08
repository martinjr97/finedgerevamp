@extends('layouts.customer')

@section('title', 'Support & Help')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-2xl font-bold text-white mb-1">Support & Help</h1>
            <p class="text-sm text-blue-100">Submit a support ticket and our team will get back to you using your registered contact details.</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Contact Info --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your Contact Details</h2>
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 space-y-3 shadow-md">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Name</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $customer->full_name }}</p>
                        </div>
                    </div>
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Email</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $customer->email ?? 'Not provided' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Phone</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $customer->phone ?? 'Not provided' }}
                            </p>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                        We will use your registered email or phone number to contact you about this request.
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 space-y-3 shadow-md">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.129a11.042 11.042 0 005.516 5.516l1.129-2.257a1 1 0 011.21-.502l4.493 1.498A1 1 0 0121 18.72V22a2 2 0 01-2 2h-1C9.82 24 4 18.18 4 11V10a2 2 0 012-2h0"/>
                        </svg>
                        Need urgent help?
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        You can also call us on
                        <span class="font-semibold">{{ config('app.support_phone', '+260 000 000 000') }}</span>
                        during working hours.
                    </p>
                </div>
            </div>

            {{-- Support Form --}}
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-md space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Submit a Support Ticket</h2>

                <form method="POST" action="{{ route('customer.support.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    {{-- Hidden fields to reuse existing validation & logic --}}
                    <input type="hidden" name="name" value="{{ $customer->full_name }}">
                    <input type="hidden" name="email" value="{{ $customer->email }}">
                    <input type="hidden" name="phone" value="{{ old('phone', $customer->phone) }}">

                    <div class="space-y-1.5">
                        <label for="subject" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Subject</label>
                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            value="{{ old('subject') }}"
                            required
                            class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none px-4 py-2.5 text-sm"
                            placeholder="Brief summary of your issue or question"
                        >
                        @error('subject')
                            <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="message" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Message</label>
                        <textarea
                            id="message"
                            name="message"
                            rows="4"
                            required
                            class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none px-4 py-2.5 text-sm resize-y"
                            placeholder="Describe your issue or question in detail"
                        >{{ old('message') }}</textarea>
                        @error('message')
                            <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="attachment" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Supporting file (optional)</label>
                        <input type="file" id="attachment" name="attachment" accept=".pdf,image/jpeg,image/png,image/jpg"
                            class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-4 py-2 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:file:bg-blue-900/40 dark:file:text-blue-200">
                        <p class="text-xs text-gray-500">{{ \App\Support\DocumentUploadRules::HINT_PDF_IMAGE }}</p>
                        @error('attachment')
                            <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/40 hover:from-blue-600 hover:via-indigo-600 hover:to-purple-700 hover:shadow-xl transition"
                        >
                            Submit Support Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 shadow-md space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Previous Support Tickets</h2>
                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $supportTickets->count() }} Recent
                </span>
            </div>

            @if($supportTickets->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">
                    You have not submitted any support tickets yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach($supportTickets as $ticket)
                        @php
                            $statusClasses = [
                                'new' => 'border-blue-200 bg-blue-100 text-blue-700',
                                'in_progress' => 'border-amber-200 bg-amber-100 text-amber-700',
                                'resolved' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
                                'closed' => 'border-slate-200 bg-slate-100 text-slate-700',
                            ];
                            $statusClass = $statusClasses[$ticket->status] ?? 'border-slate-200 bg-slate-100 text-slate-700';
                            $statusLabel = ucwords(str_replace('_', ' ', $ticket->status ?? 'new'));
                        @endphp

                        <div x-data="{ openTicketModal: false }" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Ticket #{{ $ticket->id }} • {{ $ticket->created_at?->format('d M Y, h:i A') ?? 'N/A' }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($ticket->message, 110) }}</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                    <a
                                        href="{{ route('customer.support-tickets.show', $ticket) }}"
                                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        View &amp; Reply
                                    </a>
                                </div>
                            </div>

                            <div
                                x-show="openTicketModal"
                                x-cloak
                                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="ticket-modal-title-{{ $ticket->id }}"
                                @keydown.escape.window="openTicketModal = false"
                            >
                                <div class="absolute inset-0 bg-slate-900/50" @click="openTicketModal = false"></div>

                                <div class="relative w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                        <h3 id="ticket-modal-title-{{ $ticket->id }}" class="text-lg font-bold text-slate-900">
                                            Support Ticket #{{ $ticket->id }}
                                        </h3>
                                        <button
                                            type="button"
                                            @click="openTicketModal = false"
                                            class="rounded-lg border border-slate-300 px-2 py-1 text-slate-600 transition hover:bg-slate-50"
                                            aria-label="Close ticket details"
                                        >
                                            ✕
                                        </button>
                                    </div>

                                    <div class="space-y-4 px-5 py-4">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ $statusLabel }}</p>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</p>
                                                <p class="mt-1 font-semibold text-slate-900">{{ $ticket->created_at?->format('d M Y, h:i A') ?? 'N/A' }}</p>
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Message</p>
                                                <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $ticket->message }}</p>
                                            </div>
                                            @if($ticket->resolution_note)
                                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 sm:col-span-2">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Support Response</p>
                                                    <p class="mt-1 whitespace-pre-line text-sm text-emerald-900">{{ $ticket->resolution_note }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="border-t border-slate-200 px-5 py-4">
                                        <button
                                            type="button"
                                            @click="openTicketModal = false"
                                            class="w-full rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700"
                                        >
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection

