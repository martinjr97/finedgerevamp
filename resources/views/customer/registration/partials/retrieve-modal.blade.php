@php
    $shouldOpenOnLoad = $errors->has('reference') || $errors->has('retrieve');
@endphp

<div class="text-center {{ $triggerClass ?? 'mb-6' }}">
    <button
        type="button"
        data-retrieve-modal-open
        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white/90 px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-blue-400 hover:bg-blue-50 hover:text-blue-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
    >
        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        Already submitted a request?
    </button>
    <p class="mt-2 text-xs text-slate-600">Retrieve your application using your Registration Request ID</p>
</div>

<div
    id="registration-retrieve-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    style="display: {{ $shouldOpenOnLoad ? 'flex' : 'none' }};"
    role="dialog"
    aria-modal="true"
    aria-labelledby="registration-retrieve-modal-title"
    @if($shouldOpenOnLoad) aria-hidden="false" @else aria-hidden="true" @endif
>
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-retrieve-modal-close></div>

    <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 id="registration-retrieve-modal-title" class="text-lg font-bold text-slate-900">Retrieve your application</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Enter the Registration Request ID from your confirmation email or message.
                </p>
            </div>
            <button
                type="button"
                data-retrieve-modal-close
                class="shrink-0 rounded-lg border border-slate-300 px-2.5 py-1 text-slate-600 transition hover:bg-slate-50"
                aria-label="Close"
            >
                ✕
            </button>
        </div>

        <form method="POST" action="{{ route('customer.register-request.retrieve') }}" class="px-5 py-4 space-y-4">
            @csrf
            <div>
                <label for="retrieve_reference_modal" class="block text-sm font-medium text-slate-800">Registration Request ID</label>
                <input
                    id="retrieve_reference_modal"
                    name="reference"
                    type="text"
                    value="{{ old('reference') }}"
                    required
                    autocomplete="off"
                    class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25"
                    placeholder="e.g. CRR-20251218-AB12CD"
                >
                @error('reference')
                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
                @error('retrieve')
                    <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <p class="text-xs text-slate-500">
                You can update your details if your application is still pending or has been returned for editing.
            </p>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    data-retrieve-modal-close
                    class="inline-flex justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                >
                    Retrieve application
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const modal = document.getElementById('registration-retrieve-modal');
                if (!modal) return;

                const openModal = () => {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                    const input = modal.querySelector('#retrieve_reference_modal');
                    if (input) {
                        window.setTimeout(() => input.focus(), 50);
                    }
                };

                const closeModal = () => {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                };

                document.querySelectorAll('[data-retrieve-modal-open]').forEach((btn) => {
                    btn.addEventListener('click', openModal);
                });

                modal.querySelectorAll('[data-retrieve-modal-close]').forEach((el) => {
                    el.addEventListener('click', closeModal);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.style.display !== 'none') {
                        closeModal();
                    }
                });

                if (modal.style.display !== 'none') {
                    document.body.classList.add('overflow-hidden');
                }
            })();
        </script>
    @endpush
@endonce
