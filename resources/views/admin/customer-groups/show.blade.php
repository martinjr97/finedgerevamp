@extends('layouts.admin')

@section('title', 'Customer Group: '.$customerGroup->name.' | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $customerGroup->name,
            'description' => 'Customer Group Details',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Product',
                    'href' => route('admin.loan-products.show', $customerGroup->loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ],
                [
                    'action' => 'secondary',
                    'text' => 'Manage Rate Type',
                    'href' => route('admin.customer-groups.manage-rate-type', $customerGroup),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>'
                ]
            ]
        ])

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Group Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Group Information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Name:</span>
                        <span class="font-medium text-white">{{ $customerGroup->name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Code:</span>
                        <span class="text-xs text-slate-400 font-mono">{{ $customerGroup->code }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Product:</span>
                        <a href="{{ route('admin.loan-products.show', $customerGroup->loanProduct) }}" class="font-medium text-cyan-400 hover:text-cyan-300 hover:underline transition">
                            {{ $customerGroup->loanProduct->name }}
                        </a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Risk Level:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customerGroup->risk_level === 'low' ? 'bg-emerald-500/20 text-emerald-300' : ($customerGroup->risk_level === 'medium' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                            {{ ucfirst($customerGroup->risk_level) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customerGroup->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                            {{ $customerGroup->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-1">Relationship Manager</span>
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm">
                                @if($customerGroup->relationshipManager)
                                    <p class="font-medium text-white">{{ $customerGroup->relationshipManager->full_name }}</p>
                                    <p class="text-xs text-slate-400">{{ $customerGroup->relationshipManager->email }}</p>
                                @else
                                    <p class="text-xs text-slate-400">Not assigned</p>
                                @endif
                            </div>
                            <button type="button" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-500 px-3 py-2 text-xs font-semibold text-white shadow-md shadow-cyan-500/30 js-open-rm-modal">
                                {{ $customerGroup->relationshipManager ? 'Change' : 'Assign' }}
                            </button>
                        </div>
                    </div>
                    @if($customerGroup->description)
                        <div>
                            <span class="text-slate-400 block mb-1">Description:</span>
                            <p class="text-white">{{ $customerGroup->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Financial Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Financial Information</h2>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 px-3 py-2 text-xs font-semibold text-white shadow-md shadow-amber-500/30 js-open-financial-modal">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5h2m-1 0v14m-7-7h14"/>
                        </svg>
                        Edit Financial Settings
                    </button>
                </div>
                <div class="space-y-3 text-sm">
                    @if($customerGroup->loanRateType)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Rate Type:</span>
                            <div class="text-right">
                                <span class="font-medium text-white">{{ $customerGroup->loanRateType->name }}</span>
                                <p class="text-xs text-slate-400 font-mono">{{ $customerGroup->loanRateType->code }}</p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Rate Type:</span>
                            <span class="text-slate-500 text-xs">Not assigned</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Max Loan Amount:</span>
                        <span class="font-medium text-white">{{ $customerGroup->max_loan_amount ? 'ZMW '.number_format($customerGroup->max_loan_amount, 2) : '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Max Loan Tenure:</span>
                        <span class="font-medium text-white">{{ $customerGroup->max_loan_tenure_months ? $customerGroup->max_loan_tenure_months.' months' : '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Allow multiple active loans:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $customerGroup->allow_multiple_loans ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' }}">
                            {{ $customerGroup->allow_multiple_loans ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    @if($customerGroup->instalment_cross_over_percentage)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Instalment Cross Over:</span>
                            <span class="font-medium text-white">{{ number_format($customerGroup->instalment_cross_over_percentage, 2) }}%</span>
                        </div>
                    @endif
                    @if($customerGroup->maximum_debit_ratio)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Maximum Debit Ratio:</span>
                            <span class="font-medium text-white">{{ number_format($customerGroup->maximum_debit_ratio, 2) }}</span>
                        </div>
                    @endif
                    @if($customerGroup->loan_cut_off_day)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Loan Cut-off Day:</span>
                            <span class="font-medium text-white">{{ $customerGroup->loan_cut_off_day }}<sup>{{ $customerGroup->loan_cut_off_day == 1 || $customerGroup->loan_cut_off_day == 21 || $customerGroup->loan_cut_off_day == 31 ? 'st' : ($customerGroup->loan_cut_off_day == 2 || $customerGroup->loan_cut_off_day == 22 ? 'nd' : ($customerGroup->loan_cut_off_day == 3 || $customerGroup->loan_cut_off_day == 23 ? 'rd' : 'th')) }}</sup></span>
                        </div>
                    @endif
                    @if($customerGroup->loan_payment_date)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Loan Payment Date:</span>
                            <span class="font-medium text-white">{{ $customerGroup->loan_payment_date }}<sup>{{ $customerGroup->loan_payment_date == 1 || $customerGroup->loan_payment_date == 21 || $customerGroup->loan_payment_date == 31 ? 'st' : ($customerGroup->loan_payment_date == 2 || $customerGroup->loan_payment_date == 22 ? 'nd' : ($customerGroup->loan_payment_date == 3 || $customerGroup->loan_payment_date == 23 ? 'rd' : 'th')) }}</sup></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Relationship Manager History --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Relationship Manager History
                </h2>
            </div>
            @php
                $histories = $customerGroup->relationshipManagerHistories->sortByDesc('started_at');
            @endphp
            @if($histories->count() > 0)
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
                            @foreach($histories as $history)
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
            @else
                <p class="text-center text-slate-400 py-6 text-sm">No relationship manager history recorded yet.</p>
            @endif
        </div>

        {{-- Customers List --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Customers in this Group
                </h2>
                <span class="rounded-full bg-cyan-500/20 px-3 py-1 text-sm font-medium text-cyan-300">
                    {{ $customerGroup->customers->count() }} {{ $customerGroup->customers->count() == 1 ? 'Customer' : 'Customers' }}
                </span>
            </div>
            @if($customerGroup->customers->count() > 0)
                <div class="overflow-x-auto">
                    <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base">Name</th>
                                <th class="px-4 py-4 text-base">Email</th>
                                <th class="px-4 py-4 text-base">Phone</th>
                                <th class="px-4 py-4 text-base">Status</th>
                                <th class="px-4 py-4 text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customerGroup->customers as $customer)
                                <tr class="border-t border-white/5 text-center">
                                    <td class="px-4 py-3 font-medium text-white">{{ $customer->full_name }}</td>
                                    <td class="px-4 py-3">{{ $customer->email }}</td>
                                    <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs {{ $customer->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($customer->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                            {{ ucfirst($customer->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-full bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-medium text-blue-300 hover:bg-blue-500/30 hover:border-blue-500 transition">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center text-slate-400 py-12">No customers are currently assigned to this group.</p>
            @endif
        </div>
    </div>

    {{-- Relationship Manager Modal --}}
    <div id="relationshipManagerModal" class="fixed inset-0 z-40 hidden">
        <div class="absolute inset-0 z-40 bg-slate-900/60 js-close-rm-modal"></div>
        <div class="relative z-50 flex h-full w-full items-start justify-end overflow-y-auto px-4 py-8">
            <div class="w-full max-w-md rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-white">Update Relationship Manager</h3>
                        <p class="text-sm text-slate-400">Assign or change the relationship manager for this group.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-white js-close-rm-modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form id="relationshipManagerForm" action="{{ route('admin.customer-groups.update-relationship-manager', $customerGroup) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="text-sm font-medium text-slate-200">Relationship Manager</label>
                        <select name="relationship_manager_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 text-sm">
                            <option value="">Not assigned</option>
                            @foreach($relationshipManagers as $manager)
                                <option value="{{ $manager->id }}" @selected(old('relationship_manager_id', $customerGroup->relationship_manager_id) == $manager->id)>
                                    {{ $manager->full_name }} ({{ $manager->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('relationship_manager_id')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-slate-200 mb-1">Reason for change <span class="text-rose-400" title="Required when changing an existing manager">*</span></label>
                        <textarea name="change_reason" rows="3" class="w-full rounded-2xl bg-white/5 border border-white/10 text-white px-3 py-2 text-sm focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Explain why you are changing the relationship manager (required if one is already assigned)">{{ old('change_reason') }}</textarea>
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

    {{-- Financial Settings Modal --}}
    <div id="financialSettingsModal" class="fixed inset-0 z-40 hidden">
        <div class="absolute inset-0 z-40 bg-slate-900/60 js-close-financial-modal"></div>
        <div class="relative z-50 flex h-full w-full items-start justify-end overflow-y-auto px-4 py-8">
            <div class="w-full max-w-md rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-white">Edit Financial Settings</h3>
                        <p class="text-sm text-slate-400">Update limits and schedule configuration for this group.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-white js-close-financial-modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form id="financialSettingsForm"
                      action="{{ route('admin.customer-groups.update-financial', $customerGroup) }}"
                      method="POST"
                      class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-200">Max Loan Amount</label>
                        <input type="number"
                               name="max_loan_amount"
                               value="{{ old('max_loan_amount', $customerGroup->max_loan_amount) }}"
                               step="1"
                               min="0"
                               class="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:ring-amber-500/40 focus:outline-none">
                        @error('max_loan_amount')
                            <p class="text-xs text-rose-400 font-medium">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400">Maximum loan amount for this group.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-200">Loan Cut-off Day</label>
                            <input type="number"
                                   name="loan_cut_off_day"
                                   value="{{ old('loan_cut_off_day', $customerGroup->loan_cut_off_day) }}"
                                   min="1"
                                   max="31"
                                   class="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:ring-amber-500/40 focus:outline-none">
                            @error('loan_cut_off_day')
                                <p class="text-xs text-rose-400 font-medium">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-slate-400">Day of month when payroll cycle closes.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-200">Loan Pay Date</label>
                            <input type="number"
                                   name="loan_payment_date"
                                   value="{{ old('loan_payment_date', $customerGroup->loan_payment_date) }}"
                                   min="1"
                                   max="31"
                                   class="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:ring-amber-500/40 focus:outline-none">
                            @error('loan_payment_date')
                                <p class="text-xs text-rose-400 font-medium">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-slate-400">Expected salary/pay date.</p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="allow_multiple_loans" value="0">
                            <input type="checkbox"
                                   name="allow_multiple_loans"
                                   value="1"
                                   @checked(old('allow_multiple_loans', $customerGroup->allow_multiple_loans))
                                   class="mt-1 rounded border-white/20 bg-black/30 text-amber-500 focus:ring-amber-500/40">
                            <span>
                                <span class="block text-sm font-medium text-slate-200">Allow multiple active loans</span>
                                <span class="block text-xs text-slate-400 mt-1">If disabled, customers in this group can only have one pending, approved, or active loan at a time.</span>
                            </span>
                        </label>
                        @error('allow_multiple_loans')
                            <p class="text-xs text-rose-400 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-200">Maximum Debit Ratio (%)</label>
                            <input type="number"
                                   name="maximum_debit_ratio"
                                   value="{{ old('maximum_debit_ratio', $customerGroup->maximum_debit_ratio) }}"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   class="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:ring-amber-500/40 focus:outline-none">
                            @error('maximum_debit_ratio')
                                <p class="text-xs text-rose-400 font-medium">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-slate-400">Max allowed percentage of salary for deductions.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-200">Instalment Cross-over (%)</label>
                            <input type="number"
                                   name="instalment_cross_over_percentage"
                                   value="{{ old('instalment_cross_over_percentage', $customerGroup->instalment_cross_over_percentage) }}"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   class="w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-amber-500 focus:ring-amber-500/40 focus:outline-none">
                            @error('instalment_cross_over_percentage')
                                <p class="text-xs text-rose-400 font-medium">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-slate-400">Allowance for instalments crossing periods.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button"
                                class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-black/20 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-black/30 js-close-financial-modal">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 hover:from-amber-600 hover:to-orange-600 transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('relationshipManagerModal');
            const openButtons = document.querySelectorAll('.js-open-rm-modal');
            const closeButtons = document.querySelectorAll('.js-close-rm-modal');
            const form = document.getElementById('relationshipManagerForm');
            const currentValue = "{{ $customerGroup->relationship_manager_id ?? '' }}";

            if (!modal || !form) {
                return;
            }

            const toggleModal = (show) => {
                if (show) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                } else {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            };

            openButtons.forEach(btn => btn.addEventListener('click', (event) => {
                event.preventDefault();
                toggleModal(true);
            }));

            closeButtons.forEach(btn => btn.addEventListener('click', () => toggleModal(false)));

            form.addEventListener('submit', (event) => {
                const select = form.querySelector('select[name="relationship_manager_id"]');
                const reason = form.querySelector('textarea[name="change_reason"]');
                const newValue = select.value || '';

                // Only prompt and require reason when changing an existing assignment
                if (currentValue && currentValue !== newValue) {
                    if (!reason.value.trim()) {
                        event.preventDefault();
                        alert('Please provide a reason for changing the relationship manager.');
                        return;
                    }

                    if (!confirm('You are changing the relationship manager for this group. This action will be recorded in the history. Do you want to proceed?')) {
                        event.preventDefault();
                        return;
                    }
                }
            });

            const hasRmErrors = {{ ($errors->has('relationship_manager_id') || $errors->has('change_reason')) ? 'true' : 'false' }};
            if (hasRmErrors) {
                toggleModal(true);
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const financialModal = document.getElementById('financialSettingsModal');
            const openFinancialButtons = document.querySelectorAll('.js-open-financial-modal');
            const closeFinancialButtons = document.querySelectorAll('.js-close-financial-modal');

            if (!financialModal) {
                return;
            }

            const toggleFinancialModal = (show) => {
                if (show) {
                    financialModal.classList.remove('hidden');
                    financialModal.classList.add('flex');
                } else {
                    financialModal.classList.add('hidden');
                    financialModal.classList.remove('flex');
                }
            };

            openFinancialButtons.forEach(btn => btn.addEventListener('click', (event) => {
                event.preventDefault();
                toggleFinancialModal(true);
            }));

            closeFinancialButtons.forEach(btn => btn.addEventListener('click', () => toggleFinancialModal(false)));

            const hasFinancialErrors = {{ ($errors->has('max_loan_amount') || $errors->has('loan_cut_off_day') || $errors->has('loan_payment_date') || $errors->has('maximum_debit_ratio') || $errors->has('instalment_cross_over_percentage')) ? 'true' : 'false' }};
            if (hasFinancialErrors) {
                toggleFinancialModal(true);
            }
        });
    </script>
@endpush

