@extends('layouts.admin')

@section('title', 'Customer Request Details | ' . config('app.system_name'))

@section('content')
    @php
        use App\Support\PublicRegistrationPaths;
        $employment = $request->employment_details ?? [];
        $collateral = $request->collateral_details ?? [];
        $pathLabel = $request->registration_path
            ? PublicRegistrationPaths::label($request->registration_path)
            : ($request->product?->name ?? '—');
    @endphp
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customer Request Details',
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

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Summary --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-3">Summary</h2>

                    <dl class="space-y-2 text-sm text-slate-200">
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Reference</dt>
                            <dd class="font-medium text-white">{{ $request->reference ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <dt class="text-slate-400">Registration path</dt>
                            <dd>
                                <span class="inline-flex items-center rounded-full border border-cyan-500/40 bg-cyan-500/10 px-2.5 py-0.5 text-xs font-semibold text-cyan-200">
                                    {{ $pathLabel }}
                                </span>
                            </dd>
                        </div>
                        @if($request->requested_loan_amount)
                            <div class="flex justify-between">
                                <dt class="text-slate-400">Requested loan</dt>
                                <dd class="font-medium text-white">{{ number_format((float) $request->requested_loan_amount, 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Status</dt>
                            @php
                                $status = $request->status ?? 'pending';
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
                            <dd class="font-semibold {{ $statusColor }}">{{ $statusLabel }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Submitted on</dt>
                            <dd>
                                <div>{{ $request->created_at?->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $request->created_at?->format('H:i') }}</div>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Last updated</dt>
                            <dd>
                                <div>{{ $request->updated_at?->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $request->updated_at?->format('H:i') }}</div>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 space-y-2">
                        @if($request->status === 'pending' || $request->status === 'reverted')
                            <form method="POST" action="{{ route('admin.customer-requests.approve', $request) }}" class="w-full">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-2xl bg-emerald-500/20 border border-emerald-400/70 px-4 py-2.5 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/40 hover:border-emerald-300 transition"
                                >
                                    Start Review
                                </button>
                            </form>
                            <button
                                type="button"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-2xl bg-rose-500/20 border border-rose-400/70 px-4 py-2.5 text-sm font-semibold text-rose-100 hover:bg-rose-500/40 hover:border-rose-300 transition js-open-reject-modal"
                            >
                                Reject
                            </button>
                        @elseif(($request->status === 'rejected' || $request->status === 'approved') && !$request->created_customer_id)
                            <button
                                type="button"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-2xl bg-amber-500/20 border border-amber-400/70 px-4 py-2.5 text-sm font-semibold text-amber-100 hover:bg-amber-500/40 hover:border-amber-300 transition js-open-revert-modal"
                            >
                                Revert for Editing
                            </button>
                        @endif

                        @if($request->status === 'approved' && !$request->created_customer_id && auth('admin')->user()?->can('customers.create'))
                            <div class="pt-3 mt-3 border-t border-white/10">
                                <a
                                    href="{{ route('admin.customers.create', ['product_id' => $request->loan_product_id, 'registration_request' => $request->id]) }}"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-2xl bg-blue-500/20 border border-blue-400/70 px-4 py-2.5 text-sm font-semibold text-blue-100 hover:bg-blue-500/40 hover:border-blue-300 transition mt-2"
                                >
                                    Create Customer
                                </a>
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
                                    <p class="text-sm text-slate-400">
                                        Add instructions for the applicant and optionally email them to take action.
                                    </p>
                                </div>
                                <button type="button" class="text-slate-400 hover:text-white js-close-revert-modal">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <form method="POST" action="{{ route('admin.customer-requests.revert', $request) }}" class="space-y-4">
                                @csrf
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
                                        @disabled(empty($request->email))
                                    >
                                    <label for="notify_applicant" class="text-sm text-slate-200">
                                        Email applicant
                                        <span class="text-slate-400">
                                            {{ $request->email ? '(' . $request->email . ')' : '(no email on file)' }}
                                        </span>
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

                {{-- Reject confirmation modal --}}
                <div id="rejectRequestModal" class="fixed inset-0 z-40 hidden" aria-modal="true" role="dialog" aria-label="Reject customer request modal">
                    <div class="absolute inset-0 z-40 bg-slate-900/60 js-close-reject-modal"></div>
                    <div class="relative z-50 flex h-full w-full items-start justify-end overflow-y-auto px-4 py-8">
                        <div class="w-full max-w-md rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-white">Reject Request</h3>
                                    <p class="text-sm text-slate-400">
                                        Are you sure you want to reject this registration request?
                                    </p>
                                </div>
                                <button type="button" class="text-slate-400 hover:text-white js-close-reject-modal">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                                <div class="font-semibold text-rose-100">
                                    {{ $request->reference ?? '—' }} — {{ trim(($request->first_name ?? '').' '.($request->last_name ?? '')) ?: 'Applicant' }}
                                </div>
                                <div class="mt-1 text-xs text-rose-100/80">
                                    This will mark the request as rejected. You can still revert it for editing later if needed.
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.customer-requests.reject', $request) }}" class="mt-4 space-y-4">
                                @csrf
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white js-close-reject-modal">
                                        Cancel
                                    </button>
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-rose-400 to-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-500/30">
                                        Yes, Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @if($request->created_customer_id && $request->createdCustomer)
                    <div class="rounded-3xl border border-emerald-500/40 bg-emerald-500/10 p-5 shadow-lg">
                        <h2 class="text-lg font-semibold text-slate-100 mb-3">Created Customer</h2>
                        <div class="grid gap-3 md:grid-cols-2 text-sm text-slate-200">
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Customer</div>
                                <div class="mt-1 text-base">
                                    <a href="{{ route('admin.customers.show', $request->createdCustomer) }}" class="underline text-emerald-200 hover:text-emerald-100">
                                        {{ $request->createdCustomer->full_name }} (ID: {{ $request->createdCustomer->id }})
                                    </a>
                                </div>
                            </div>
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Created on</div>
                                <div class="mt-1 text-base">
                                    {{ $request->created_customer_at?->format('d M Y H:i') ?? '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Created by</div>
                                <div class="mt-1 text-base">
                                    {{ $request->createdByAdmin?->full_name ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-3">Technical</h2>
                    <dl class="space-y-2 text-xs text-slate-300">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400">IP Address</dt>
                            <dd class="text-right">{{ $request->ip_address ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400 mb-1">User Agent</dt>
                            <dd class="text-[11px] leading-snug text-slate-400 break-words">
                                {{ $request->user_agent ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Details --}}
            <div class="lg:col-span-2 space-y-6">
                @if($hasDuplicates)
                    <div class="rounded-3xl border border-amber-500/40 bg-amber-500/10 p-5 shadow-lg">
                        <h2 class="text-lg font-semibold text-slate-100 mb-3">Possible existing customers</h2>
                        <p class="text-sm text-amber-200 mb-3">
                            The system has found existing customers that share some details with this request.
                            The badges below show what information is matching to help you decide whether to use an existing customer or create a new one.
                        </p>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach($duplicateMatches as $key => $entry)
                                @if($entry['customers']->isEmpty())
                                    @continue
                                @endif
                                <div class="rounded-2xl border border-amber-500/40 bg-amber-500/5 p-3">
                                    <div class="text-xs font-semibold text-amber-200 uppercase tracking-wide">
                                        {{ $entry['label'] }}
                                    </div>
                                    @if($entry['value'])
                                        <div class="mt-1 text-sm text-amber-200">
                                            Matching value: <span class="font-semibold">{{ $entry['value'] }}</span>
                                        </div>
                                    @endif
                                    <ul class="mt-2 space-y-1.5 text-xs text-slate-200">
                                        @foreach($entry['customers'] as $customer)
                                            <li class="flex items-center justify-between gap-2">
                                                <div>
                                                    <div class="font-semibold text-slate-200">
                                                        {{ $customer->full_name }} (ID: {{ $customer->id }})
                                                    </div>
                                                    <div class="mt-0.5 inline-flex flex-wrap gap-1">
                                                        <span class="inline-flex items-center rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-200">
                                                            Shares {{ $entry['label'] }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-1 text-[11px] text-slate-300">
                                                        NRC: {{ $customer->national_id ?? '—' }} |
                                                        Phone: {{ $customer->phone ?? '—' }} |
                                                        Email: {{ $customer->email ?? '—' }}
                                                    </div>
                                                </div>
                                                @can('customers.view')
                                                    <a
                                                        href="{{ route('admin.customers.show', $customer) }}"
                                                        class="inline-flex items-center rounded-full bg-white/10 px-2 py-1 text-[11px] font-semibold text-slate-200 hover:bg-white/20"
                                                    >
                                                        View
                                                    </a>
                                                @endcan
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-4">Applicant Details</h2>
                    <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-200">
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">Full Name</div>
                            <div class="mt-1 text-base">
                                {{ $request->first_name }} {{ $request->last_name }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">National ID</div>
                            <div class="mt-1 text-base">
                                {{ $request->national_id ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">Phone</div>
                            <div class="mt-1 text-base">
                                {{ $request->phone ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">Email</div>
                            <div class="mt-1 text-base">
                                {{ $request->email ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">TPIN</div>
                            <div class="mt-1 text-base">
                                {{ $request->tpin ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-4">Internal assignment</h2>
                    <p class="text-xs text-slate-400 mb-3">Mapped from registration path. Assign customer group when creating the customer.</p>
                    <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-200">
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">Loan product (internal)</div>
                            <div class="mt-1 text-base">{{ $request->product->name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400 text-xs uppercase tracking-wide">Customer group</div>
                            <div class="mt-1 text-base">{{ $request->group->name ?? 'To be assigned' }}</div>
                        </div>
                    </div>
                </div>

                @if($request->registration_path === PublicRegistrationPaths::GOVERNMENT_WORKER && !empty($employment))
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                        <h2 class="text-lg font-semibold text-white mb-4">Employment details</h2>
                        <div class="grid gap-3 md:grid-cols-2 text-sm text-slate-200">
	                            @foreach($employment as $key => $value)
	                                @if($value !== null && $value !== '')
	                                    <div>
	                                        <div class="text-slate-400 text-xs uppercase tracking-wide">{{ \Illuminate\Support\Str::of($key)->replace('_', ' ')->title() }}</div>
	                                        <div class="mt-1 text-base">{{ $key !== 'employee_number' && is_numeric($value) ? number_format((float) $value, 2) : $value }}</div>
	                                    </div>
	                                @endif
	                            @endforeach
	                        </div>
	                    </div>
                @endif

                @if($request->registration_path === PublicRegistrationPaths::COLLATERAL_BASED && !empty($collateral))
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                        <h2 class="text-lg font-semibold text-white mb-4">Collateral details</h2>
                        <div class="grid gap-3 md:grid-cols-2 text-sm text-slate-200">
                            @foreach($collateral as $key => $value)
                                @if($value !== null && $value !== '')
                                    <div @class(['md:col-span-2' => $key === 'collateral_description'])>
                                        <div class="text-slate-400 text-xs uppercase tracking-wide">{{ \Illuminate\Support\Str::of($key)->replace('_', ' ')->title() }}</div>
                                        <div class="mt-1 text-base">{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-4">KYC Documents</h2>
                    @if(!empty($kycDocuments))
                        <ul class="space-y-2 text-sm text-slate-200">
                            @foreach($kycDocuments as $document)
                                <li class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2">
                                    <span class="font-medium text-slate-100">
                                        {{ $document['label'] }}
                                    </span>
                                    <a
                                        href="{{ asset('storage/'.$document['path']) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-xs font-semibold text-cyan-300 hover:text-cyan-100 underline shrink-0"
                                    >
                                        View
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-400">No KYC documents were uploaded with this request.</p>
                    @endif
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg">
                    <h2 class="text-lg font-semibold text-white mb-4">Additional Details</h2>
                    @if(!empty($payload))
                        <div class="grid gap-3 md:grid-cols-2 text-sm text-slate-200">
                            @foreach($payload as $key => $value)
                                @if(is_array($value))
                                    @continue
                                @endif
                                <div>
                                    <div class="text-slate-400 text-xs uppercase tracking-wide">
                                        {{ \Illuminate\Support\Str::of($key)->replace('_', ' ')->title() }}
                                    </div>
                                    <div class="mt-1 text-base">
                                        {{ $value === '' || $value === null ? '—' : $value }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-400">No additional details captured.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

	@push('scripts')
	    <script>
	        (() => {
	            const revertModal = document.getElementById('revertForEditingModal');
	            const revertOpenButtons = document.querySelectorAll('.js-open-revert-modal');
	            const revertCloseButtons = document.querySelectorAll('.js-close-revert-modal');

	            const rejectModal = document.getElementById('rejectRequestModal');
	            const rejectOpenButtons = document.querySelectorAll('.js-open-reject-modal');
	            const rejectCloseButtons = document.querySelectorAll('.js-close-reject-modal');

	            const openModal = (modal) => {
	                if (!modal) return;
	                modal.classList.remove('hidden');
	                modal.classList.add('flex');
	                document.body.classList.add('modal-open');
	            }

	            const closeModal = (modal) => {
	                if (!modal) return;
	                modal.classList.add('hidden');
	                modal.classList.remove('flex');
	                document.body.classList.remove('modal-open');
	            }

	            if (revertModal && revertOpenButtons.length > 0) {
	                revertOpenButtons.forEach((btn) => btn.addEventListener('click', () => openModal(revertModal)));
	                revertCloseButtons.forEach((btn) => btn.addEventListener('click', () => closeModal(revertModal)));
	            }

	            if (rejectModal && rejectOpenButtons.length > 0) {
	                rejectOpenButtons.forEach((btn) => btn.addEventListener('click', () => openModal(rejectModal)));
	                rejectCloseButtons.forEach((btn) => btn.addEventListener('click', () => closeModal(rejectModal)));
	            }

	            @if($errors->has('revert_reason'))
	                openModal(revertModal);
	            @endif
	        })();
	    </script>
	@endpush
