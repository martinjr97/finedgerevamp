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
                        @can('customers.view')
                            <a
                                href="{{ route('admin.customers.index', ['company_id' => $company->id]) }}"
                                class="group inline-flex items-center gap-2 text-3xl font-semibold text-white transition hover:text-cyan-300"
                                title="View customers for {{ $company->name }}"
                            >
                                {{ number_format($company->customers_count) }}
                                <svg class="h-5 w-5 text-slate-500 opacity-0 transition group-hover:text-cyan-300 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        @else
                            <p class="text-3xl font-semibold text-white">{{ number_format($company->customers_count) }}</p>
                        @endcan
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Relationship Admins</p>
                        <p class="text-3xl font-semibold text-white">{{ $company->admins_count }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Active Loans</p>
                        @can('loans.view')
                            <a
                                href="{{ route('admin.loans.index', ['status' => 'active', 'disbursement_status' => 'completed']) }}"
                                class="group inline-flex items-center gap-2 text-3xl font-semibold text-white transition hover:text-cyan-300"
                                title="View active loans"
                            >
                                {{ number_format($loanSnapshot['active_loans_count']) }}
                                <svg class="h-5 w-5 text-slate-500 opacity-0 transition group-hover:text-cyan-300 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        @else
                            <p class="text-3xl font-semibold text-white">{{ number_format($loanSnapshot['active_loans_count']) }}</p>
                        @endcan
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Total Outstanding Balance</p>
                        <p class="text-2xl font-semibold text-amber-300">
                            ZMW {{ number_format($loanSnapshot['total_outstanding_balance'], 2) }}
                        </p>
                    </div>
                    @if($loanSnapshot['has_overdue'])
                        <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4">
                            <p class="text-xs uppercase tracking-wide text-rose-200 mb-2">Overdue Exposure</p>
                            <p class="text-2xl font-semibold text-rose-300">
                                ZMW {{ number_format($loanSnapshot['total_overdue_amount'], 2) }}
                            </p>
                            <p class="mt-1 text-sm text-rose-200/90">
                                {{ number_format($loanSnapshot['overdue_loans_count']) }} {{ $loanSnapshot['overdue_loans_count'] === 1 ? 'loan' : 'loans' }} with overdue installments
                            </p>
                            @can('reports.view')
                                <a
                                    href="{{ route('admin.reports.arrears', ['company_id' => $company->id]) }}"
                                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-rose-200 hover:text-white transition"
                                >
                                    View arrears report
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @endcan
                        </div>
                    @endif
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
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase text-slate-400 mb-1">Relationship Manager</p>
                                <div id="relationshipManagerDisplay">
                                    @if($company->relationshipManager)
                                        <p>{{ $company->relationshipManager->full_name }}</p>
                                        <p class="text-xs text-slate-400">{{ $company->relationshipManager->email }}</p>
                                    @else
                                        <p>Not assigned</p>
                                    @endif
                                </div>
                            </div>
                            @can('companies.update')
                                <button type="button" class="inline-flex shrink-0 items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-500 px-3 py-2 text-xs font-semibold text-white shadow-md shadow-cyan-500/30 js-open-rm-modal">
                                    {{ $company->relationshipManager ? 'Change' : 'Assign' }}
                                </button>
                            @endcan
                        </div>
                        @php
                            $relationshipManagerHistoryCount = $company->relationshipManagerHistories->count();
                        @endphp
                        @if($relationshipManagerHistoryCount > 0)
                            <button
                                type="button"
                                class="mt-3 inline-flex items-center gap-2 text-xs font-medium text-cyan-300 hover:text-cyan-200 transition js-toggle-rm-history"
                                aria-expanded="false"
                                aria-controls="relationshipManagerHistoryPanel"
                            >
                                <svg class="h-4 w-4 js-rm-history-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                                View relationship manager history ({{ $relationshipManagerHistoryCount }})
                            </button>
                        @endif
                    </div>
                </div>
                <div class="border-t border-white/10 pt-4 text-sm text-white space-y-1">
                    <p class="text-xs uppercase text-slate-400 mb-1">Address</p>
                    @if($company->address_line1)
                        <p>{{ $company->address_line1 }}</p>
                    @endif
                    <p>{{ collect([$company->city, $company->state, $company->postal_code])->filter()->implode(', ') }}</p>
                    @if($company->country)
                        <p>{{ $company->country }}</p>
                    @endif
                </div>
            </div>
        </div>

        @php
            $relationshipManagerHistories = $company->relationshipManagerHistories->sortByDesc('started_at');
        @endphp
        @if($relationshipManagerHistories->count() > 0)
            <div id="relationshipManagerHistoryPanel" class="hidden rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-white">Relationship Manager History</h3>
                    <button type="button" class="text-xs font-medium text-slate-400 hover:text-white transition js-toggle-rm-history">
                        Hide history
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-3">Manager</th>
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3">Changed By</th>
                                <th class="px-4 py-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($relationshipManagerHistories as $history)
                                <tr class="border-t border-white/5 text-center">
                                    <td class="px-4 py-3">
                                        @if($history->relationshipManager)
                                            <div class="font-medium text-white">{{ $history->relationshipManager->full_name }}</div>
                                            <div class="text-xs text-slate-400">{{ $history->relationshipManager->email }}</div>
                                        @else
                                            <span class="text-slate-400 text-xs">Manager removed</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-white text-xs">
                                            {{ $history->started_at?->format('d M Y H:i') ?? '—' }}
                                            <span class="text-slate-400">→</span>
                                            {{ $history->ended_at?->format('d M Y H:i') ?? 'Present' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($history->changedBy)
                                            <div class="text-white text-xs">{{ $history->changedBy->full_name }}</div>
                                            <div class="text-xs text-slate-400">{{ $history->changedBy->email }}</div>
                                        @else
                                            <span class="text-slate-400 text-xs">System</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-left align-top">
                                        {{ $history->change_reason ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @can('companies.update')
        <div id="relationshipManagerModal" class="fixed inset-0 z-40 hidden">
            <div class="absolute inset-0 z-40 bg-slate-900/60 js-close-rm-modal"></div>
            <div class="relative z-50 flex h-full w-full items-start justify-end overflow-y-auto px-4 py-8">
                <div class="w-full max-w-md rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-white">Update Relationship Manager</h3>
                            <p class="text-sm text-slate-400">Assign or change the relationship manager for this company.</p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-white js-close-rm-modal">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <form id="relationshipManagerForm" action="{{ route('admin.companies.update-relationship-manager', $company) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="text-sm font-medium text-slate-200">Relationship Manager</label>
                            <select name="relationship_manager_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 text-sm">
                                <option value="">Not assigned</option>
                                @foreach($relationshipManagers as $manager)
                                    <option value="{{ $manager->id }}" @selected(old('relationship_manager_id', $company->relationship_manager_id) == $manager->id)>
                                        {{ $manager->full_name }} ({{ $manager->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('relationship_manager_id')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-slate-200 mb-1">
                                Reason for change
                                @if($company->relationship_manager_id)
                                    <span class="text-rose-400" title="Required when changing an existing manager">*</span>
                                @endif
                            </label>
                            <textarea name="change_reason" rows="3" class="w-full rounded-2xl bg-white/5 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Explain why you are changing the relationship manager">{{ old('change_reason') }}</textarea>
                            @error('change_reason')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white js-close-rm-modal">
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
    @endcan

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
            const rmModal = document.getElementById('relationshipManagerModal');
            const rmOpenButtons = document.querySelectorAll('.js-open-rm-modal');
            const rmCloseButtons = document.querySelectorAll('.js-close-rm-modal');
            const rmHistoryPanel = document.getElementById('relationshipManagerHistoryPanel');
            const rmHistoryToggles = document.querySelectorAll('.js-toggle-rm-history');
            const rmHistoryChevron = document.querySelector('.js-rm-history-chevron');

            const toggleRmHistory = () => {
                if (!rmHistoryPanel) {
                    return;
                }

                const isHidden = rmHistoryPanel.classList.contains('hidden');
                rmHistoryPanel.classList.toggle('hidden', !isHidden);
                rmHistoryToggles.forEach((button) => {
                    button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                });
                if (rmHistoryChevron) {
                    rmHistoryChevron.classList.toggle('rotate-180', isHidden);
                }
            };

            rmHistoryToggles.forEach((button) => {
                button.addEventListener('click', toggleRmHistory);
            });

            if (rmModal) {
                const toggleRmModal = (show) => {
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
                        rmModal.classList.remove('hidden');
                        rmModal.classList.add('flex');
                    } else {
                        document.body.classList.remove('modal-open');
                        document.body.style.paddingRight = '';
                        rmModal.classList.add('hidden');
                        rmModal.classList.remove('flex');
                    }
                };

                rmOpenButtons.forEach((button) => button.addEventListener('click', (event) => {
                    event.preventDefault();
                    toggleRmModal(true);
                }));

                rmCloseButtons.forEach((button) => button.addEventListener('click', () => toggleRmModal(false)));

                @if($errors->has('relationship_manager_id') || $errors->has('change_reason'))
                    toggleRmModal(true);
                @endif
            }

            @if($errors->has('relationship_manager_id') || $errors->has('change_reason'))
                if (rmHistoryPanel) {
                    rmHistoryPanel.classList.remove('hidden');
                    rmHistoryToggles.forEach((button) => button.setAttribute('aria-expanded', 'true'));
                    if (rmHistoryChevron) {
                        rmHistoryChevron.classList.add('rotate-180');
                    }
                }
            @endif

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
