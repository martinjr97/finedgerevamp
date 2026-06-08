@extends('layouts.admin')

@section('title', $loanRateType->name . ' | '.config('app.system_name'))

@section('content')
    @php
        use App\Services\LoanRateRowService;

        $rowService = app(LoanRateRowService::class);
        $hasImportErrors = $errors->has('rates_file');
        $hasCopyErrors = $errors->has('target_loan_product_id')
            || $errors->has('name')
            || $errors->has('code')
            || $errors->has('description');
    @endphp

    <div
        class="space-y-8"
        x-data="{ openImportRatesModal: {{ $hasImportErrors ? 'true' : 'false' }}, openCopyRatesModal: {{ $hasCopyErrors ? 'true' : 'false' }} }"
        x-on:keydown.escape.window="openImportRatesModal = false; openCopyRatesModal = false"
    >
        @include('partials.admin.page-header', [
            'title' => $loanRateType->name,
            'description' => $loanRateType->description ?? 'Loan rate type details and rates',
            'buttons' => array_filter([
                [
                    'action' => 'secondary',
                    'text' => 'Back to Rate Types',
                    'href' => route('admin.loan-rate-types.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                auth('admin')->user()?->can('loan-rate-types.update') ? [
                    'action' => 'edit',
                    'text' => 'Edit Rate Type',
                    'href' => route('admin.loan-rate-types.edit', $loanRateType),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>'
                ] : null,
                auth('admin')->user()?->can('loan-rate-types.update') ? [
                    'action' => 'import',
                    'text' => 'Import Rates',
                    'href' => '#',
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12l-3-3m3 3l3-3M4 20h16"/></svg>',
                    'attributes' => [
                        'x-on:click.prevent' => 'openImportRatesModal = true',
                    ],
                ] : null,
                auth('admin')->user()?->can('loan-rate-types.delete') && ($rateTypeDeletion['allowed'] ?? false) ? [
                    'action' => 'danger',
                    'text' => 'Delete Rate Type',
                    'href' => '#',
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
                    'attributes' => [
                        'x-on:click.prevent' => "if (confirm('Delete this rate type and all unused rate rows? This cannot be undone.')) { document.getElementById('delete-rate-type-form').submit(); }",
                    ],
                ] : null,
                auth('admin')->user()?->can('loan-rate-types.update') && $targetLoanProducts->isNotEmpty() ? [
                    'action' => 'secondary',
                    'text' => 'Copy to Product',
                    'href' => '#',
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2M10 20h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
                    'class' => '!border-purple-400/60 !text-purple-700 hover:!bg-purple-50',
                    'attributes' => [
                        'x-on:click.prevent' => 'openCopyRatesModal = true',
                    ],
                ] : null,
            ])
        ])

        @can('loan-rate-types.delete')
            @if($rateTypeDeletion['allowed'] ?? false)
                <form id="delete-rate-type-form" method="POST" action="{{ route('admin.loan-rate-types.destroy', $loanRateType) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @elseif(! empty($rateTypeDeletion['reasons'] ?? []))
                <div class="rounded-2xl border border-amber-500/40 bg-amber-950/30 px-4 py-3 text-sm text-amber-200">
                    This rate type cannot be deleted: {{ implode(' ', $rateTypeDeletion['reasons']) }}
                </div>
            @endif
        @endcan

        {{-- Rate Type Information --}}
        <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-blue-500"></span>Rate Type Information
            </h2>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Code</p>
                    <p class="text-sm font-medium text-white">{{ $loanRateType->code }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Loan Product</p>
                    <span class="inline-block rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                        {{ $loanRateType->loanProduct->name }}
                    </span>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Interest Behavior</p>
                    <span class="inline-block rounded-full bg-purple-500/20 px-2 py-1 text-xs text-purple-300">
                        {{ $rowService->interestBehaviorLabel($loanRateType->interest_behavior) }}
                    </span>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Rate Entry Method</p>
                    <span class="inline-block rounded-full bg-indigo-500/20 px-2 py-1 text-xs text-indigo-300">
                        {{ $rowService->rateEntryMethodLabel($loanRateType->rate_input_mode) }}
                    </span>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Status</p>
                    <span class="inline-block rounded-full px-2 py-1 text-xs {{ $loanRateType->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                        {{ $loanRateType->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Loan Rates Section --}}
        <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-blue-500"></span>Loan Rates
                </h2>
                @can('loan-rate-types.update')
                    <a href="{{ route('admin.loan-rate-types.rates.create', $loanRateType) }}" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add New Rate
                    </a>
                @endcan
            </div>

            @if($loanRateType->loanRates->count() > 0)
                <div class="overflow-x-auto">
                    <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base">Tenure</th>
                                <th class="px-4 py-4 text-base">Proc. Fee</th>
                                <th class="px-4 py-4 text-base">Term %</th>
                                <th class="px-4 py-4 text-base">Daily</th>
                                <th class="px-4 py-4 text-base">Weekly</th>
                                <th class="px-4 py-4 text-base">Derived Daily</th>
                                <th class="px-4 py-4 text-base">Principal Band</th>
                                <th class="px-4 py-4 text-base">Arrear</th>
                                <th class="px-4 py-4 text-base">Status</th>
                                <th class="px-4 py-4 text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loanRateType->loanRates as $rate)
                                <tr class="border-t border-white/5 text-center">
                                    <td class="px-4 py-3 font-medium text-white">{{ $rate->tenure_months }}</td>
                                    <td class="px-4 py-3">{{ number_format($rate->processing_fee_percentage, 2) }}%</td>
                                    <td class="px-4 py-3">{{ $rate->term_interest_percentage !== null ? number_format($rate->term_interest_percentage, 2).'%' : '—' }}</td>
                                    <td class="px-4 py-3">{{ $rate->daily_rate !== null ? number_format($rate->daily_rate, 5) : '—' }}</td>
                                    <td class="px-4 py-3">{{ $rate->weekly_rate !== null ? number_format($rate->weekly_rate, 5) : '—' }}</td>
                                    <td class="px-4 py-3">{{ $rate->derived_daily_rate !== null ? number_format($rate->derived_daily_rate, 8) : '—' }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        @if($rate->min_principal === null && $rate->max_principal === null)
                                            All amounts
                                        @else
                                            {{ $rate->min_principal !== null ? number_format($rate->min_principal, 2) : 'open' }}
                                            –
                                            {{ $rate->max_principal !== null ? number_format($rate->max_principal, 2) : 'open' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ number_format($rate->arrear_rate, 5) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs {{ $rate->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                            {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            @can('loan-rate-types.update')
                                                <a href="{{ route('admin.loan-rate-types.rates.edit', [$loanRateType, $rate]) }}" class="rounded-full bg-amber-500/20 border border-amber-500/50 px-3 py-1.5 text-xs font-medium text-amber-300 hover:bg-amber-500/30 hover:border-amber-500 transition">Edit</a>
                                            @endcan
                                            @can('loan-rate-types.delete')
                                                @if($rateRowDeletable[$rate->id] ?? false)
                                                    <form method="POST" action="{{ route('admin.loan-rate-types.rates.destroy', [$loanRateType, $rate]) }}" class="inline" onsubmit="return confirm('Delete this rate row? This cannot be undone.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-full bg-rose-500/20 border border-rose-500/50 px-3 py-1.5 text-xs font-medium text-rose-300 hover:bg-rose-500/30 hover:border-rose-500 transition">Delete</button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-slate-500" title="Used on loans">In use</span>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-slate-400 text-center py-8">No rates configured for this rate type. Click "Add New Rate" to get started.</p>
            @endif
        </div>

        @can('loan-rate-types.update')
            <div
                x-cloak
                x-show="openImportRatesModal"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-label="Import rates modal"
            >
                <div class="absolute inset-0 bg-slate-950/70" x-on:click="openImportRatesModal = false"></div>

                <div class="relative w-full max-w-2xl rounded-3xl border border-cyan-500/30 bg-slate-900 p-6 shadow-2xl">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Import Rates from Excel/CSV</h3>
                            <p class="mt-1 text-sm text-slate-300">
                                Use the <span class="font-medium text-white">Rates</span> sheet. Required:
                                <span class="font-medium text-white">tenure_months, processing_fee_percentage</span>
                                @if(($loanRateType->rate_input_mode ?? 'daily_multiplier') === 'term_percentage')
                                    and <span class="font-medium text-white">term_interest_percentage</span>.
                                @elseif(($loanRateType->rate_input_mode ?? '') === 'weekly_multiplier' || $loanRateType->accrual_period === 'weekly')
                                    and <span class="font-medium text-white">weekly_rate</span>.
                                @else
                                    and <span class="font-medium text-white">daily_rate</span>.
                                @endif
                                Processing fee is separate from interest. See Instructions sheet in the template.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl border border-white/20 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10"
                            x-on:click="openImportRatesModal = false"
                        >
                            Close
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.loan-rate-types.rates.import', $loanRateType) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Rates File</label>
                            <input
                                type="file"
                                name="rates_file"
                                accept=".xlsx,.xls,.csv"
                                required
                                class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500/20 file:text-cyan-300 hover:file:bg-cyan-500/30"
                            >
                            @error('rates_file')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-slate-400">Accepted formats: .xlsx, .xls, .csv (max 10MB)</p>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <a href="{{ route('admin.loan-rate-types.rates.template', $loanRateType) }}" class="inline-flex items-center gap-2 rounded-2xl border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-300 hover:bg-cyan-500/20 transition">
                                Download Template
                            </a>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                                Import Rates
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if($targetLoanProducts->isNotEmpty())
                <div
                    x-cloak
                    x-show="openCopyRatesModal"
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Copy rates modal"
                >
                    <div class="absolute inset-0 bg-slate-950/70" x-on:click="openCopyRatesModal = false"></div>

                    <div class="relative w-full max-w-2xl rounded-3xl border border-purple-500/30 bg-slate-900 p-6 shadow-2xl">
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Copy Rates to Another Product</h3>
                                <p class="mt-1 text-sm text-slate-300">Clone this rate type and all rates to the target product.</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl border border-white/20 px-3 py-1.5 text-sm text-slate-300 hover:bg-white/10"
                                x-on:click="openCopyRatesModal = false"
                            >
                                Close
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.loan-rate-types.copy-product', $loanRateType) }}" class="space-y-4" onsubmit="return confirm('Copy these rates to the selected product?');">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Target Product <span class="text-rose-400">*</span></label>
                                <select name="target_loan_product_id" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-purple-400 focus:ring-purple-400/40">
                                    <option value="">Select product</option>
                                    @foreach($targetLoanProducts as $targetProduct)
                                        <option value="{{ $targetProduct->id }}" @selected(old('target_loan_product_id') == $targetProduct->id)>
                                            {{ $targetProduct->name }} ({{ $targetProduct->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('target_loan_product_id')
                                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-300">New Rate Type Name (Optional)</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-purple-400 focus:ring-purple-400/40" placeholder="{{ $loanRateType->name }}">
                                @error('name')
                                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-300">New Rate Type Code (Optional)</label>
                                <input type="text" name="code" value="{{ old('code') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-purple-400 focus:ring-purple-400/40" placeholder="Auto-generated if blank">
                                @error('code')
                                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-300">Description (Optional)</label>
                                <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-purple-400 focus:ring-purple-400/40" placeholder="Defaults to source description">{{ old('description') }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <button type="button" class="rounded-2xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-white/10" x-on:click="openCopyRatesModal = false">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl border border-purple-500/40 bg-purple-500/10 px-4 py-2 text-sm font-semibold text-purple-300 hover:bg-purple-500/20 transition">
                                    Copy to Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>
@endsection
