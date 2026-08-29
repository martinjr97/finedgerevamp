@extends('layouts.admin')

@section('title', 'Create Support Ticket | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Support Ticket',
            'description' => 'Log a support request on behalf of a customer or guest.',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Tickets',
                    'href' => route('admin.support-tickets.index'),
                ],
            ],
        ])

        <form
            method="POST"
            action="{{ route('admin.support-tickets.store') }}"
            enctype="multipart/form-data"
            class="w-full space-y-6"
            x-data="{
                customerId: @js(old('customer_id', '')),
                name: @js(old('name', '')),
                email: @js(old('email', '')),
                phone: @js(old('phone', '')),
                applyCustomer(data) {
                    if (!data) {
                        return;
                    }
                    this.name = data.name || this.name;
                    this.email = data.email || this.email;
                    this.phone = data.phone || this.phone;
                }
            }"
        >
            @csrf

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5">
                        <h2 class="text-lg font-semibold text-white">Requester</h2>

                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-slate-200">Linked customer (optional)</label>
                            <select
                                id="customer_id"
                                name="customer_id"
                                data-no-select-search="true"
                                data-search-placeholder="Search by name, phone, NRC, employee no…"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:ring-cyan-400/40 focus:outline-none"
                            >
                                <option value="">— Guest / manual entry —</option>
                                @if ($selectedCustomer)
                                    <option
                                        value="{{ $selectedCustomer->id }}"
                                        data-name="{{ $selectedCustomer->full_name }}"
                                        data-email="{{ $selectedCustomer->email ?? '' }}"
                                        data-phone="{{ $selectedCustomer->phone ?? '' }}"
                                        selected
                                    >
                                        {{ $selectedCustomer->full_name }} @if($selectedCustomer->phone)({{ $selectedCustomer->phone }})@endif
                                    </option>
                                @endif
                            </select>
                            <p class="mt-1 text-xs text-slate-400">Type at least 2 characters to search all customers.</p>
                            @error('customer_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-200">Name <span class="text-rose-400" x-show="!customerId">*</span></label>
                                <input type="text" id="name" name="name" x-model="name" :required="!customerId"
                                    class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                                @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-200">Email</label>
                                <input type="email" id="email" name="email" x-model="email"
                                    class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                                @error('email')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="phone" class="block text-sm font-medium text-slate-200">Phone</label>
                                <input type="text" id="phone" name="phone" x-model="phone" maxlength="12" placeholder="260970000000"
                                    class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none zambian-phone-input">
                                @error('phone')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5">
                        <h2 class="text-lg font-semibold text-white">Ticket details</h2>

                        <div>
                            <label for="subject" class="block text-sm font-medium text-slate-200">Subject <span class="text-rose-400">*</span></label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                                placeholder="Brief summary of the issue">
                            @error('subject')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-slate-200">Message <span class="text-rose-400">*</span></label>
                            <textarea id="message" name="message" rows="6" required
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-3 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none resize-y min-h-[10rem]"
                                placeholder="Describe the issue in detail">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="attachment" class="block text-sm font-medium text-slate-200">Supporting file (optional)</label>
                            <input type="file" id="attachment" name="attachment" accept=".pdf,image/jpeg,image/png,image/jpg"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/30 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-cyan-100 hover:file:bg-cyan-500/40">
                            <p class="mt-1 text-xs text-slate-400">{{ \App\Support\DocumentUploadRules::HINT_PDF_IMAGE }}</p>
                            @error('attachment')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5 lg:sticky lg:top-6">
                        <h2 class="text-lg font-semibold text-white">Assignment</h2>
                        <p class="text-sm text-slate-400">Optionally assign this ticket when it is created.</p>
                        <div>
                            <label for="assigned_to_id" class="block text-sm font-medium text-slate-200">Assign to staff</label>
                            <select id="assigned_to_id" name="assigned_to_id"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                                <option value="">— Unassigned —</option>
                                @foreach ($staffMembers as $staff)
                                    <option value="{{ $staff->id }}" @selected((string) old('assigned_to_id') === (string) $staff->id)>
                                        {{ $staff->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="assignment_note" class="block text-sm font-medium text-slate-200">Assignment note</label>
                            <textarea id="assignment_note" name="assignment_note" rows="4"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none resize-y"
                                placeholder="Optional note for the assignee">{{ old('assignment_note') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-3xl border border-white/10 bg-white/5 px-6 py-4 shadow-lg">
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 text-base font-semibold text-white shadow-lg hover:from-cyan-600 hover:to-blue-700 transition">
                    Create Ticket
                </button>
                <a href="{{ route('admin.support-tickets.index') }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-white/20 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const initCustomerSearch = () => {
        const select = document.getElementById('customer_id');
        if (!select || typeof TomSelect === 'undefined' || select.tomselect) {
            return;
        }

        const form = select.closest('form');
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const searchUrl = @js(route('admin.support-tickets.search-customers'));

        const alpineData = () => {
            if (!form || typeof Alpine === 'undefined' || typeof Alpine.$data !== 'function') {
                return null;
            }

            try {
                return Alpine.$data(form);
            } catch (e) {
                return null;
            }
        };

        const fillRequesterFields = (option) => {
            if (!option) {
                return;
            }

            const name = option.name || '';
            const email = option.email || '';
            const phone = option.phone || '';

            if (nameInput) {
                nameInput.value = name;
                nameInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (emailInput) {
                emailInput.value = email;
                emailInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (phoneInput) {
                phoneInput.value = phone;
                phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            const data = alpineData();
            if (data) {
                data.customerId = String(option.id || '');
                data.name = name;
                data.email = email;
                data.phone = phone;
            }
        };

        const clearCustomerBinding = () => {
            const data = alpineData();
            if (data) {
                data.customerId = '';
            }
        };

        new TomSelect(select, {
            create: false,
            maxItems: 1,
            allowEmptyOption: true,
            valueField: 'id',
            labelField: 'text',
            searchField: ['text', 'phone', 'email', 'national_id', 'name'],
            preload: false,
            loadThrottle: 300,
            placeholder: select.dataset.searchPlaceholder || 'Search customers…',
            load(query, callback) {
                if (!query || query.length < 2) {
                    callback();
                    return;
                }

                const url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('q', query);

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then((response) => response.json())
                    .then((payload) => callback(payload.customers || []))
                    .catch(() => callback());
            },
            render: {
                option(data, escape) {
                    const meta = [data.phone, data.national_id].filter(Boolean).join(' · ');
                    return `<div>
                        <div class="font-medium">${escape(data.name || data.text || '')}</div>
                        ${meta ? `<div class="text-xs opacity-70">${escape(meta)}</div>` : ''}
                    </div>`;
                },
                item(data, escape) {
                    return `<div>${escape(data.text || data.name || '')}</div>`;
                },
                no_results(data, escape) {
                    return `<div class="no-results">No matches for "${escape(data.input)}"</div>`;
                },
            },
            onItemAdd(value) {
                const option = this.options[value];
                if (option) {
                    // Ensure name/phone exist even if Tom Select only kept text from a native <option>
                    if (!option.name && option.$option) {
                        option.name = option.$option.dataset.name || '';
                        option.email = option.$option.dataset.email || '';
                        option.phone = option.$option.dataset.phone || '';
                    }
                    fillRequesterFields(option);
                }
            },
            onClear() {
                clearCustomerBinding();
            },
            onChange(value) {
                if (!value) {
                    clearCustomerBinding();
                    return;
                }

                const option = this.options[value];
                if (option) {
                    if (!option.name && option.$option) {
                        option.name = option.$option.dataset.name || '';
                        option.email = option.$option.dataset.email || '';
                        option.phone = option.$option.dataset.phone || '';
                    }
                    fillRequesterFields(option);
                }
            },
        });

        select.dataset.selectSearchInit = 'true';
    };

    const start = () => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCustomerSearch, { once: true });
        } else {
            initCustomerSearch();
        }
    };

    document.addEventListener('alpine:initialized', initCustomerSearch, { once: true });
    window.addEventListener('load', initCustomerSearch, { once: true });
    start();
})();
</script>
@endpush
