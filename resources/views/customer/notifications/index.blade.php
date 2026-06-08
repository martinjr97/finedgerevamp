@extends('layouts.customer')

@section('title', 'Notifications')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="bg-gradient-to-r from-slate-800 via-slate-700 to-slate-900 rounded-2xl p-6 shadow-xl border border-slate-600">
            <h1 class="text-2xl font-bold text-white">Notifications</h1>
            <p class="text-sm text-slate-200 mt-1">
                Email and SMS updates related to your repayments, loans, and account activity.
            </p>
        </div>

        @if($communications->isEmpty())
            <div class="rounded-2xl border border-slate-300 bg-white p-8 text-center shadow">
                <p class="text-slate-700 font-semibold">No notifications yet.</p>
                <p class="text-sm text-slate-500 mt-2">When updates are sent to you, they will appear here.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-300 bg-white shadow overflow-hidden">
                <ul class="divide-y divide-slate-200">
                    @foreach($communications as $communication)
                        @php
                            $type = strtolower((string) ($communication->type ?? 'email'));
                            $typeClasses = [
                                'email' => 'border-blue-200 bg-blue-50 text-blue-700',
                                'sms' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'both' => 'border-violet-200 bg-violet-50 text-violet-700',
                            ];
                            $badgeClass = $typeClasses[$type] ?? 'border-slate-200 bg-slate-50 text-slate-700';
                            $sentAt = $communication->sent_at ?? $communication->created_at;
                            $subject = $communication->masked_subject ?: 'Notification';
                            $message = $communication->masked_message ?? '';
                            $preview = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($message), 160);
                            $notificationData = json_encode([
                                'id' => $communication->id,
                                'subject' => $subject,
                                'type' => $type,
                                'sentAt' => $sentAt?->format('d M Y, h:i A') ?? 'N/A',
                                'message' => $message,
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                        @endphp
                        <li class="p-4 sm:p-5 hover:bg-slate-50 transition">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h2 class="text-base font-semibold text-slate-900 truncate">{{ $subject }}</h2>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ $sentAt?->format('d M Y, h:i A') ?? 'N/A' }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-600">
                                        {{ $preview !== '' ? $preview : 'No preview available.' }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 sm:pl-4">
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $badgeClass }}">
                                        {{ strtoupper($type) }}
                                    </span>
                                    <button
                                        type="button"
                                        class="notification-view-btn inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                        data-notification="{{ $notificationData }}"
                                    >
                                        View
                                    </button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                {{ $communications->links() }}
            </div>
        @endif

        <div
            id="notification-modal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display: none;"
            role="dialog"
            aria-modal="true"
            aria-labelledby="notification-modal-title"
        >
            <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>

            <div class="relative w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 id="notification-modal-title" class="text-lg font-bold text-slate-900">Notification</h2>
                        <p id="notification-modal-sent-at" class="mt-1 text-xs text-slate-500"></p>
                    </div>
                    <button
                        type="button"
                        data-modal-close
                        class="rounded-lg border border-slate-300 px-2 py-1 text-slate-600 transition hover:bg-slate-50"
                        aria-label="Close notification"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div>
                        <span
                            id="notification-modal-type"
                            class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700"
                        >
                            NOTIFICATION
                        </span>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p id="notification-modal-message" class="text-sm leading-relaxed whitespace-pre-line text-slate-700"></p>
                    </div>
                </div>

                <div class="border-t border-slate-200 px-5 py-4">
                    <button
                        type="button"
                        data-modal-close
                        class="w-full rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('notification-modal');
            if (!modal) return;

            const titleEl = document.getElementById('notification-modal-title');
            const sentAtEl = document.getElementById('notification-modal-sent-at');
            const typeEl = document.getElementById('notification-modal-type');
            const messageEl = document.getElementById('notification-modal-message');

            const typeClassMap = {
                email: 'border-blue-200 bg-blue-50 text-blue-700',
                sms: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                both: 'border-violet-200 bg-violet-50 text-violet-700',
                default: 'border-slate-200 bg-slate-50 text-slate-700'
            };

            const resetTypeBadgeClass = () => {
                typeEl.className = 'inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide';
            };

            const openModal = (notification) => {
                const type = String(notification.type || 'notification').toLowerCase();
                const typeClass = typeClassMap[type] || typeClassMap.default;

                titleEl.textContent = notification.subject || 'Notification';
                sentAtEl.textContent = notification.sentAt || '';
                messageEl.textContent = notification.message || '';

                resetTypeBadgeClass();
                typeEl.classList.add(...typeClass.split(' '));
                typeEl.textContent = type.toUpperCase();

                modal.style.display = 'flex';
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = () => {
                modal.style.display = 'none';
                document.body.classList.remove('overflow-hidden');
            };

            document.querySelectorAll('.notification-view-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const raw = button.getAttribute('data-notification');
                    if (!raw) return;

                    try {
                        openModal(JSON.parse(raw));
                    } catch (error) {
                        // Fail-safe: keep page usable if one payload is malformed.
                        console.error('Failed to parse notification payload', error);
                    }
                });
            });

            modal.querySelectorAll('[data-modal-close]').forEach((closeElement) => {
                closeElement.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.style.display !== 'none') {
                    closeModal();
                }
            });
        })();
    </script>
@endpush
