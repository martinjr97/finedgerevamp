@extends('layouts.admin')

@section('title', 'Market · '.$market->name)

@section('content')
    @php
        $customerDetails = $market->marketeerCustomerDetails;
    @endphp
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $market->name,
            'description' => trim(sprintf('%s • %s, %s', $market->code, $market->province->name ?? 'Unknown province', $market->district->name ?? 'Unknown district')),
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Markets',
                    'href' => route('admin.markets.index'),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m11 14H4"/></svg>'
                ],
                [
                    'action' => 'edit',
                    'text' => 'Edit Market',
                    'href' => route('admin.markets.edit', $market),
                    'icon' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.732-6.732a2.121 2.121 0 013 3L12 14l-4 1 1-4z"/></svg>'
                ],
            ]
        ])

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-cyan-200">Market Overview</p>
                        <h2 class="mt-2 text-2xl font-semibold text-white">{{ $market->name }}</h2>
                        <p class="text-sm text-slate-300 mt-1">{{ $market->address_line1 }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                            Code: {{ $market->code }}
                        </span>
                        <span class="inline-flex items-center rounded-full border {{ $market->is_active ? 'border-emerald-400/60 bg-emerald-500/20 text-emerald-100' : 'border-rose-400/60 bg-rose-500/20 text-rose-100' }} px-3 py-1 text-xs font-semibold">
                            {{ $market->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-300 mb-1">Province</p>
                        <p class="text-lg font-semibold text-white">{{ $market->province->name ?? '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-300 mb-1">District</p>
                        <p class="text-lg font-semibold text-white">{{ $market->district->name ?? '—' }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs uppercase text-slate-300 mb-1">City / Town</p>
                        <p class="text-lg font-semibold text-white">{{ $market->city ?? '—' }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-cyan-400/20 bg-cyan-500/5 p-4 text-sm text-slate-200">
                    <p class="font-semibold text-white">Loan Rate Type</p>
                    @if($market->loanRateType)
                        <p class="mt-1 text-base text-cyan-200">{{ $market->loanRateType->name }}</p>
                        <p class="text-xs text-slate-300 uppercase tracking-[0.3em]">Accrual: {{ strtoupper($market->loanRateType->accrual_period) }}</p>
                    @else
                        <p class="mt-1 text-slate-300">No loan rate type linked. Assign one to enable pricing.</p>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-xl space-y-5">
                <div class="flex items-center gap-2">
                    <div class="rounded-xl bg-cyan-500/20 p-2">
                        <svg class="w-5 h-5 text-cyan-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M8 5V3h8v2m-4 0v16m-5 0h10a2 2 0 002-2V7H5v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">Key Contacts</h3>
                </div>
                <div class="space-y-3 text-sm text-white">
                    <div>
                        <p class="text-xs uppercase text-slate-400 mb-1">Contact Person</p>
                        <p>{{ $market->contact_person_name }}</p>
                        <p class="text-xs text-slate-300">{{ $market->contact_person_phone }}</p>
                        @if($market->contact_person_email)
                            <p class="text-xs text-slate-300">{{ $market->contact_person_email }}</p>
                        @endif
                    </div>
                    <div class="border-t border-white/10 pt-3">
                        <p class="text-xs uppercase text-slate-400 mb-1">Portfolio Manager</p>
                        @if($market->portfolioManager)
                            <p>{{ $market->portfolioManager->full_name }}</p>
                            <p class="text-xs text-slate-300">{{ $market->portfolioManager->email }}</p>
                        @else
                            <p class="text-slate-400">Not assigned</p>
                        @endif
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                    <p class="text-xs uppercase text-slate-400 mb-1">Marketeer Customers</p>
                    <p class="text-3xl font-semibold text-white">{{ $customerDetails->count() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-xs uppercase tracking-[0.4em] text-cyan-200">Market Activity</p>
                    <h3 class="text-xl font-semibold text-white mt-2">Registered Marketeer Customers</h3>
                </div>
                <a href="{{ route('admin.customers.select-product-type', ['market_id' => $market->id]) }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/20 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Customer
                </a>
            </div>

            @if($customerDetails->isNotEmpty())
                <div class="overflow-x-auto">
                    <table data-datatable="true" data-datatable-per-page="10" class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-sm font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b border-white/10">
                                <th class="px-4 py-4 text-base">Customer</th>
                                <th class="px-4 py-4 text-base">Stand No.</th>
                                <th class="px-4 py-4 text-base">Stand Description</th>
                                <th class="px-4 py-4 text-base">Monthly Income</th>
                                <th class="px-4 py-4 text-base">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customerDetails as $detail)
                                <tr class="border-t border-white/5 text-center">
                                    <td class="px-4 py-3 text-white font-medium">
                                        {{ $detail->customer->full_name ?? '—' }}
                                        @if($detail->customer)
                                            <p class="text-xs text-slate-400">{{ $detail->customer->email }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $detail->stand_number ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-300">{{ $detail->stand_description ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $detail->monthly_income ? 'ZMW '.number_format($detail->monthly_income, 2) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($detail->customer)
                                            <span class="rounded-full px-2 py-1 text-xs {{ $detail->customer->status === 'active' ? 'bg-emerald-500/20 text-emerald-300' : ($detail->customer->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                                {{ ucfirst($detail->customer->status) }}
                                            </span>
                                        @else
                                            <span class="rounded-full px-2 py-1 text-xs bg-slate-500/20 text-slate-300">Unknown</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-center text-slate-400 py-12">No customers have been onboarded for this market yet.</p>
            @endif
        </div>
    </div>
@endsection

