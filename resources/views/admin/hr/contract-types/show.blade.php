@extends('layouts.admin')

@section('title', $contractType->name.' | Contract Types | HR | '.config('app.system_name'))

@section('content')
    @php
        $canManage = auth('admin')->user()?->can('hr.contract-types.manage') ?? false;
        $canViewContracts = auth('admin')->user()?->can('hr.contracts.view') ?? false;
        $yesNo = fn (?bool $value) => $value ? 'Yes' : 'No';
    @endphp

    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => $contractType->name,
            'description' => 'Contract category settings, leave accrual, and renewal rules.',
            'buttons' => array_filter([
                [
                    'action' => 'back',
                    'text' => 'All contract types',
                    'href' => route('admin.hr.contract-types.index'),
                ],
                $canManage ? [
                    'action' => 'edit',
                    'text' => 'Edit contract type',
                    'href' => route('admin.hr.contract-types.edit', $contractType),
                ] : null,
            ]),
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-cyan-500/15 text-lg font-bold text-cyan-200 ring-1 ring-cyan-400/30">
                        {{ strtoupper(substr($contractType->code, 0, 2)) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-semibold text-white">{{ $contractType->name }}</h2>
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $contractType->is_active ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300' : 'border-rose-400/40 bg-rose-500/10 text-rose-300' }}">
                                {{ $contractType->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-sm text-slate-400">Code: {{ $contractType->code }}</p>
                        @if ($contractType->description)
                            <p class="mt-3 max-w-2xl text-sm text-slate-300">{{ $contractType->description }}</p>
                        @endif
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm min-w-[12rem]">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Employee contracts</p>
                    <p class="mt-1 text-3xl font-bold text-white">{{ number_format($contractsCount) }}</p>
                    @if ($canViewContracts && $contractsCount > 0)
                        <a href="{{ route('admin.hr.contracts.index', ['contract_type_id' => $contractType->id]) }}" class="mt-2 inline-block text-xs font-semibold text-cyan-300 hover:underline">
                            View contracts
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h3 class="text-lg font-semibold text-white">Terms &amp; duration</h3>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Permanent contract', 'value' => $yesNo($contractType->is_permanent)])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Requires end date', 'value' => $yesNo($contractType->has_end_date)])
                @include('admin.hr.employees.partials.detail-field', [
                    'label' => 'Default duration (months)',
                    'value' => $contractType->default_duration_months !== null ? (string) $contractType->default_duration_months : null,
                ])
                @include('admin.hr.employees.partials.detail-field', [
                    'label' => 'Probation (months)',
                    'value' => $contractType->probation_months !== null ? (string) $contractType->probation_months : null,
                ])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Renewable', 'value' => $yesNo($contractType->renewable)])
            </dl>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h3 class="text-lg font-semibold text-white">Leave accrual</h3>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Accrual enabled', 'value' => $yesNo($contractType->leave_accrual_enabled)])
                @include('admin.hr.employees.partials.detail-field', [
                    'label' => 'Default leave days / month',
                    'value' => $contractType->leave_days_per_month !== null ? number_format((float) $contractType->leave_days_per_month, 2) : null,
                ])
            </dl>

            @if ($contractType->leaveRules->isNotEmpty())
                <div class="admin-data-table pt-2">
                    <table class="min-w-full w-full">
                        <thead>
                            <tr>
                                <th scope="col">Leave type</th>
                                <th scope="col">Accrues monthly</th>
                                <th scope="col">Days per month</th>
                                <th scope="col">Applies to</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contractType->leaveRules->sortBy(fn ($r) => $r->leaveType?->name) as $rule)
                                <tr>
                                    <td class="font-medium text-white">{{ $rule->leaveType?->name ?? '—' }}</td>
                                    <td>{{ $rule->requires_accrual ? 'Yes' : 'No' }}</td>
                                    <td>{{ $rule->requires_accrual ? number_format((float) $rule->days_per_month, 2) : '—' }}</td>
                                    <td>{{ $rule->applicableGenderLabel() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
