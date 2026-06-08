@extends('layouts.admin')

@section('title', 'Customer Requests | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customer Requests',
            'buttons' => [],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-400/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                <div class="font-semibold mb-1">Please fix the errors below and try again.</div>
                <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-100/90">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Filters --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.customer-requests.index') }}" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Search --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Reference, name, email, phone, national ID..."
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40"
                        >
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
                        <select
                            name="status"
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40"
                        >
                            <option value="">All Statuses</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Loan Product --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Product</label>
                        <select
                            name="loan_product_id"
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40"
                        >
                            <option value="">All Products</option>
                            @foreach($loanProducts as $product)
                                <option value="{{ $product->id }}" @selected(request('loan_product_id') == $product->id)>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Customer Group --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Customer Group</label>
                        <select
                            name="customer_group_id"
                            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40"
                        >
                            <option value="">All Groups</option>
                            @foreach($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected(request('customer_group_id') == $group->id)>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition"
                    >
                        Apply Filters
                    </button>
                    <a
                        href="{{ route('admin.customer-requests.index') }}"
                        class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition"
                    >
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        {{-- List --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full text-base text-slate-300">
                    <thead>
                        <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                            <th class="px-4 py-4 text-lg border-r border-white/10">Reference</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Applicant</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Contact</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Product / Group</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Status</th>
                            <th class="px-4 py-4 text-lg border-r border-white/10">Submitted</th>
                            <th class="px-4 py-4 text-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $requestModel)
                            <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                                <td class="px-4 py-4 font-medium text-white border-r border-white/5">
                                    {{ $requestModel->reference ?? '—' }}
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="text-base font-medium text-white">
                                            {{ $requestModel->first_name }} {{ $requestModel->last_name }}
                                        </span>
                                        @if($requestModel->national_id)
                                            <span class="text-xs text-slate-400">
                                                NRC: {{ $requestModel->national_id }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="text-sm">{{ $requestModel->phone ?? '—' }}</span>
                                        <span class="text-xs text-slate-400">{{ $requestModel->email ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-sm text-cyan-300">
                                            {{ $requestModel->product->name ?? '—' }}
                                        </span>
                                        @if($requestModel->group)
                                            <span class="rounded-full bg-purple-500/20 px-2 py-1 text-xs text-purple-200 font-normal">
                                                {{ $requestModel->group->name }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    @php
                                        $status = $requestModel->status ?? 'pending';
                                        $statusLabel = match ($status) {
                                            'reverted' => 'Reverted (customer editing)',
                                            'approved' => 'In review',
                                            default => ucfirst($status),
                                        };
                                        $statusColor = match ($status) {
                                            'approved' => 'text-cyan-300',
                                            'rejected' => 'text-rose-400',
                                            default => 'text-amber-300',
                                        };
                                    @endphp
                                    <span class="text-sm font-medium {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 border-r border-white/5">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="text-sm">
                                            {{ $requestModel->created_at?->format('d M Y') }}
                                        </span>
                                        <span class="text-xs">
                                            {{ $requestModel->created_at?->format('H:i') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <a
                                            href="{{ route('admin.customer-requests.show', $requestModel) }}"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500/40 to-purple-500/40 border-2 border-blue-400/70 px-3 py-1.5 text-sm font-semibold text-blue-200 hover:from-blue-500/60 hover:to-purple-500/60 hover:border-blue-400 hover:text-white transition shadow-md shadow-blue-500/20"
                                        >
                                            View
                                        </a>

                                        @if($requestModel->status === 'pending' || $requestModel->status === 'reverted')
                                            <form method="POST" action="{{ route('admin.customer-requests.approve', $requestModel) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500/20 border border-emerald-400/60 px-3 py-1.5 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/40 hover:border-emerald-300 transition"
                                                >
                                                    Start Review
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.customer-requests.reject', $requestModel) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-500/20 border border-rose-400/60 px-3 py-1.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/40 hover:border-rose-300 transition"
                                                >
                                                    Reject
                                                </button>
                                            </form>
                                        @elseif(($requestModel->status === 'rejected' || $requestModel->status === 'approved') && !$requestModel->created_customer_id)
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500/20 border border-amber-400/60 px-3 py-1.5 text-sm font-semibold text-amber-200 hover:bg-amber-500/40 hover:border-amber-300 transition js-open-revert-modal"
                                                data-revert-action="{{ route('admin.customer-requests.revert', $requestModel) }}"
                                                data-applicant-name="{{ trim($requestModel->first_name.' '.$requestModel->last_name) }}"
                                                data-applicant-email="{{ $requestModel->email ?? '' }}"
                                                data-reference="{{ $requestModel->reference ?? '' }}"
                                            >
                                                Revert for Editing
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    No customer registration requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="mt-6 flex items-center justify-center">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Revert for editing modal --}}
    <div id="revertForEditingModal" class="fixed inset-0 z-40 hidden" aria-modal="true" role="dialog" aria-label="Revert request for editing modal">
        <div class="absolute inset-0 z-40 bg-slate-900/60 js-close-revert-modal"></div>
        <div class="relative z-50 flex h-full w-full items-start justify-end overflow-y-auto px-4 py-8">
            <div class="w-full max-w-md rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-white">Revert for Editing</h3>
                        <p class="text-sm text-slate-400" id="revertModalSubtitle">
                            Add instructions for the applicant and optionally email them to take action.
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-white js-close-revert-modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form id="revertForEditingForm" method="POST" action="{{ old('revert_action') ?: '#' }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="revert_action" id="revert_action" value="{{ old('revert_action') }}">
                    <input type="hidden" name="revert_applicant_name" id="revert_applicant_name" value="{{ old('revert_applicant_name') }}">
                    <input type="hidden" name="revert_applicant_email" id="revert_applicant_email" value="{{ old('revert_applicant_email') }}">
                    <input type="hidden" name="revert_reference" id="revert_reference" value="{{ old('revert_reference') }}">

                    <div>
                        <label class="block text-sm text-slate-200 mb-1">
                            Reason / instructions <span class="text-rose-400">*</span>
                        </label>
                        <textarea
                            name="revert_reason"
                            rows="4"
                            required
                            maxlength="2000"
                            class="w-full rounded-2xl bg-white/5 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40"
                            placeholder="Explain what the applicant must correct (e.g. missing documents, wrong ID, unclear payslip, etc.)"
                        >{{ old('revert_reason') }}</textarea>
                        @error('revert_reason')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-start gap-2">
                        <input type="hidden" name="notify_applicant" value="0">
                        <input
                            id="notify_applicant"
                            type="checkbox"
                            name="notify_applicant"
                            value="1"
                            class="mt-1 rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30"
                            @checked(old('notify_applicant', true))
                        >
                        <label for="notify_applicant" class="text-sm text-slate-200">
                            Email applicant <span class="text-slate-400" id="revertModalEmailHint"></span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                        <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white js-close-revert-modal">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-amber-400 to-orange-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg shadow-amber-500/30">
                            Revert Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('revertForEditingModal');
            const form = document.getElementById('revertForEditingForm');
            const actionInput = document.getElementById('revert_action');
            const nameInput = document.getElementById('revert_applicant_name');
            const emailInput = document.getElementById('revert_applicant_email');
            const referenceInput = document.getElementById('revert_reference');
            const emailHint = document.getElementById('revertModalEmailHint');
            const subtitle = document.getElementById('revertModalSubtitle');
            const notifyCheckbox = document.getElementById('notify_applicant');
            const openButtons = document.querySelectorAll('.js-open-revert-modal');
            const closeButtons = document.querySelectorAll('.js-close-revert-modal');

            if (!modal || !form) {
                return;
            }

            const open = (opts = {}) => {
                if (opts.action) {
                    form.action = opts.action;
                    if (actionInput) {
                        actionInput.value = opts.action;
                    }
                }

                const applicantName = opts.name || '';
                const reference = opts.reference || '';
                if (nameInput) {
                    nameInput.value = applicantName;
                }
                if (referenceInput) {
                    referenceInput.value = reference;
                }
                if (subtitle) {
                    subtitle.textContent = reference
                        ? `Revert request ${reference}${applicantName ? ` for ${applicantName}` : ''}.`
                        : 'Add instructions for the applicant and optionally email them to take action.';
                }

                const email = opts.email || '';
                if (emailInput) {
                    emailInput.value = email;
                }
                if (emailHint) {
                    emailHint.textContent = email ? `(${email})` : '(no email on file)';
                }
                if (notifyCheckbox) {
                    notifyCheckbox.disabled = !email;
                    if (!email) {
                        notifyCheckbox.checked = false;
                    } else if (opts.resetNotify) {
                        notifyCheckbox.checked = true;
                    }
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('modal-open');
            };

            const close = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('modal-open');
            };

            openButtons.forEach((btn) => {
                btn.addEventListener('click', () => open({
                    action: btn.dataset.revertAction,
                    name: btn.dataset.applicantName,
                    email: btn.dataset.applicantEmail,
                    reference: btn.dataset.reference,
                    resetNotify: true,
                }));
            });
            closeButtons.forEach((btn) => btn.addEventListener('click', close));

            @if($errors->has('revert_reason') && old('revert_action'))
                open({
                    action: @json(old('revert_action')),
                    name: @json(old('revert_applicant_name')),
                    email: @json(old('revert_applicant_email')),
                    reference: @json(old('revert_reference')),
                });
            @endif
        })();
    </script>
@endpush
