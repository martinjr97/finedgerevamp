@php
    use Illuminate\Support\Str;
    
    // Helper function to convert number to ordinal (1st, 2nd, 3rd, etc.)
    if (!function_exists('ordinal')) {
        function ordinal($number) {
            $suffix = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
            if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
                return $number . 'th';
            }
            return $number . ($suffix[$number % 10] ?? 'th');
        }
    }
@endphp

@extends('layouts.admin')

@section('title', 'Company · '.$company->name)

@section('content')
    <div class="space-y-8">
        @php
            // Use explicit visible styles: primary = navy + white text, secondary = white + dark text
            $buttons = [
                [
                    'action' => 'create',
                    'text' => 'View Customers',
                    'href' => route('admin.customers.index', ['company_id' => $company->id]),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6.5-6.13a3.5 3.5 0 11-6.999.001A3.5 3.5 0 0114.5 8zm-9 0a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z"/></svg>'
                ],
                [
                    'action' => 'create',
                    'text' => $company->loanRateType ? 'Change Loan Rate Type' : 'Manage Loan Rate Type',
                    'href' => '#',
                    'class' => 'js-open-rate-type-modal',
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>'
                ],
            ];
            
            if (auth('admin')->user()?->can('companies.update')) {
                $buttons[] = [
                    'action' => 'create',
                    'text' => 'Edit Company',
                    'href' => route('admin.companies.edit', $company),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.732-6.732a2.121 2.121 0 013 3L12 14l-4 1 1-4z"/></svg>'
                ];
            }
            
            if (auth('admin')->user()?->can('loans.view')) {
                $buttons[] = [
                    'action' => 'create',
                    'text' => 'Payment Due Report',
                    'href' => route('admin.companies.payment-due-report', $company),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                ];
            }
            
            $buttons[] = [
                'action' => 'secondary',
                'text' => 'Back',
                'href' => route('admin.companies.index'),
                'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m11 14H4"/></svg>'
            ];
        @endphp
        @include('partials.admin.page-header', [
            'title' => $company->name,
            'description' => 'Code '.$company->code.' • '.($company->sector->name ?? 'Unclassified sector'),
            'buttons' => $buttons
        ])

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-500/20 via-sky-900/20 to-transparent p-6 shadow-xl lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-cyan-200">Company Profile</p>
                        <h2 class="text-2xl font-semibold text-white mt-2">{{ $company->name }}</h2>
                        <p class="text-sm text-slate-300 mt-1">{{ $company->registration_number ?: 'Registration pending' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white">{{ ucfirst($company->type) }}</span>
                        <span class="inline-flex items-center rounded-full border {{ $company->status === 'active' ? 'border-emerald-400/60 bg-emerald-500/20 text-emerald-100' : 'border-amber-400/60 bg-amber-500/20 text-amber-100' }} px-3 py-1 text-xs font-semibold">
                            {{ ucfirst($company->status) }}
                        </span>
                        @if($company->is_primary)
                            <span class="inline-flex items-center rounded-full border border-purple-400/60 bg-purple-500/20 px-3 py-1 text-xs font-semibold text-purple-100">Primary Operator</span>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-300 mb-1">Company TPIN</p>
                        <p class="text-lg font-semibold text-white">{{ $company->tpin ?? '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-300 mb-1">MOU Expiry</p>
                        <p class="text-lg font-semibold text-white">{{ $company->mou_expiry_date ? $company->mou_expiry_date->format('d M Y') : 'Not set' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4" id="loanRateTypeCard">
                        <p class="text-xs uppercase text-slate-300 mb-1">Loan Rate Type</p>
                        <p class="text-lg font-semibold text-white" id="loanRateTypeDisplay">
                            {{ $company->loanRateType->name ?? 'Not linked' }}
                        </p>
                        <p class="text-xs text-slate-300 mt-1 capitalize" id="loanRateTypeAccrual">
                            {{ $company->loanRateType ? $company->loanRateType->accrual_period.' accrual' : 'Assign a rate type to enable pricing' }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-400 mb-1">Date of Incorporation</p>
                        <p class="text-sm font-medium text-white">{{ $company->date_of_incorporation ? $company->date_of_incorporation->format('d M Y') : '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-400 mb-1">Sector</p>
                        <p class="text-sm font-medium text-white">{{ $company->sector->name ?? 'Unclassified' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-400 mb-1">Monthly Cut-off</p>
                        <p class="text-sm font-medium text-white">{{ $company->monthly_cut_off_day ? ordinal($company->monthly_cut_off_day) : 'Not set' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-400 mb-1">Pay Day</p>
                        <p class="text-sm font-medium text-white">{{ $company->pay_day ? ordinal($company->pay_day) : 'Not set' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Snapshot</h3>
                    <span class="text-xs uppercase tracking-[0.3em] text-slate-400">Live</span>
                </div>
                <div class="space-y-6">
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Customers Linked</p>
                        <p class="text-3xl font-semibold text-white">{{ $company->customers_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Relationship Admins</p>
                        <p class="text-3xl font-semibold text-white">{{ $company->admins_count }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm text-white/80">
                        <div>
                            <p class="text-xs uppercase text-slate-500 mb-1">Created</p>
                            <p>{{ $company->created_at->format('d M Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-500 mb-1">Updated</p>
                            <p>{{ $company->updated_at->format('d M Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex items-center gap-2">
                    <div class="rounded-xl bg-cyan-500/20 p-2">
                        <svg class="w-5 h-5 text-cyan-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h2.382a1 1 0 01.894.553l.724 1.447A1 1 0 009.894 6h4.212a1 1 0 00.894-.553l.724-1.447A1 1 0 0116.618 3H19a1 1 0 011 1v2H3V4z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">Loan Programme Controls</h3>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Max Loan Tenure</p>
                        <p class="text-base font-semibold text-white">{{ $company->maximum_loan_tenure_months ?? '—' }} months</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Max Debit Ratio</p>
                        <p class="text-base font-semibold text-white">{{ number_format($company->maximum_debit_ratio ?? 40, 2) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Instalment Cross Over</p>
                        <p class="text-base font-semibold text-white">{{ number_format($company->instalment_cross_over_percentage ?? 5, 2) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Arrangement Fee</p>
                        <p class="text-base font-semibold text-white">{{ number_format($company->arrangement_fee_percentage ?? 0, 2) }}%</p>
                    </div>
                </div>
                <div class="mt-4 rounded-2xl border border-cyan-400/20 bg-cyan-500/5 p-4 text-sm text-slate-200">
                    Customers created under this company automatically inherit these limits and deductions. Adjust them anytime from the company edit form.
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5">
                <div class="flex items-center gap-2">
                    <div class="rounded-xl bg-emerald-500/20 p-2">
                        <svg class="w-5 h-5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">Contact & Relationships</h3>
                </div>
                <div class="space-y-3 text-sm text-white">
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Contact Email</p>
                        <p>{{ $company->contact_email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Contact Phone</p>
                        <p>{{ $company->contact_phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Relationship Manager</p>
                        @if($company->relationshipManager)
                            <p>{{ $company->relationshipManager->full_name }}</p>
                            <p class="text-xs text-slate-400">{{ $company->relationshipManager->email }}</p>
                        @else
                            <p>Not assigned</p>
                        @endif
                    </div>
                </div>
                <div class="border-t border-white/10 pt-4 text-sm text-white space-y-1">
                    <p class="text-xs uppercase text-slate-400 mb-1">Address</p>
                    @if($company->address_line1)
                        <p>{{ $company->address_line1 }}</p>
                    @endif
                    @if($company->address_line2)
                        <p>{{ $company->address_line2 }}</p>
                    @endif
                    <p>{{ collect([$company->city, $company->state, $company->postal_code])->filter()->implode(', ') }}</p>
                    @if($company->country)
                        <p>{{ $company->country }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="loanRateTypeModal" class="fixed inset-0 z-40 hidden">
        <div class="absolute inset-0 z-40 bg-slate-900/60 js-close-rate-type-modal"></div>
        <div class="relative z-50 flex h-full w-full items-start justify-end overflow-y-auto px-4 py-8">
            <div class="w-full max-w-md rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xl font-semibold text-white">Update Loan Rate Type</h3>
                    <p class="text-sm text-slate-400">Pick a rate configuration for this company.</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-white js-close-rate-type-modal">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="loanRateTypeForm" class="space-y-4">
                @csrf
                <div>
                    <label class="text-sm font-medium text-slate-200">Interest Rate Type</label>
                    <select name="loan_rate_type_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">— Remove link —</option>
                        @foreach ($loanRateTypes as $rateType)
                            <option value="{{ $rateType->id }}" @selected($rateType->id === $company->loan_rate_type_id)>
                                {{ $rateType->name }} ({{ strtoupper($rateType->code) }}) • {{ ucfirst($rateType->accrual_period) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white js-close-rate-type-modal">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-400 to-blue-500 px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg shadow-cyan-500/30">
                        Save Changes
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('loanRateTypeModal');
            const openButtons = document.querySelectorAll('.js-open-rate-type-modal');
            const closeButtons = document.querySelectorAll('.js-close-rate-type-modal');
            const form = document.getElementById('loanRateTypeForm');
            const display = document.getElementById('loanRateTypeDisplay');
            const accrualDisplay = document.getElementById('loanRateTypeAccrual');
            const updateUrl = "{{ route('admin.companies.loan-rate-type', $company) }}";
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : form.querySelector('input[name="_token"]').value;

            const toggleModal = (show) => {
                if (show) {
                    try {
                        window.scrollTo({ top: 0, behavior: 'instant' });
                    } catch (e) {
                        window.scrollTo(0, 0);
                    }
                    const scrollBarWidth = window.innerWidth - document.documentElement.clientWidth;
                    if (scrollBarWidth > 0) {
                        document.body.style.paddingRight = `${scrollBarWidth}px`;
                    }
                    document.body.classList.add('modal-open');
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                } else {
                    document.body.classList.remove('modal-open');
                    document.body.style.paddingRight = '';
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            };

            openButtons.forEach(btn => btn.addEventListener('click', (event) => {
                event.preventDefault();
                toggleModal(true);
            }));

            closeButtons.forEach(btn => btn.addEventListener('click', () => toggleModal(false)));

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(form);
                try {
                    const response = await fetch(updateUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    if (!response.ok) {
                        const errorData = await response.json();
                        const message = errorData.message || 'Failed to update loan rate type.';
                        Swal.fire('Error', message, 'error');
                        return;
                    }

                    const data = await response.json();
                    if (data.loan_rate_type) {
                        display.textContent = data.loan_rate_type.name;
                        accrualDisplay.textContent = `${data.loan_rate_type.accrual_period} accrual`;
                    } else {
                        display.textContent = 'Not linked';
                        accrualDisplay.textContent = 'Assign a rate type to enable pricing';
                    }

                    Swal.fire('Success', data.message, 'success');
                    toggleModal(false);
                } catch (error) {
                    Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
                }
            });
        });
    </script>
@endpush
