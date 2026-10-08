@extends('layouts.admin')

@section('title', 'HR Settings | '.config('app.system_name'))

@section('content')
    @php
        $admin = auth('admin')->user();
        $canManageContractTypes = $admin?->can('hr.contract-types.manage') ?? false;
        $canManageDepartments = $admin?->can('hr.departments.manage') ?? false;
        $canViewContractTypes = $admin?->can('hr.contract-types.view') ?? false;
        $canViewDepartments = $admin?->can('hr.departments.view') ?? false;
        $canManageSettings = $admin?->can('hr.settings.manage') ?? false;
    @endphp

    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'HR Settings',
            'description' => 'Reference data for leave, contracts, and organisational structure.',
        ])

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active leave types</p>
                <p class="mt-2 text-3xl font-bold text-cyan-300">{{ number_format($summary['leave_types']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $leaveTypes->count() }} configured in total</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active contract types</p>
                <p class="mt-2 text-3xl font-bold text-emerald-300">{{ number_format($summary['contract_types']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $contractTypes->count() }} configured in total</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active departments</p>
                <p class="mt-2 text-3xl font-bold text-white">{{ number_format($summary['departments']) }}</p>
                @if ($canViewDepartments)
                    <a href="{{ route('admin.hr.departments.index') }}" class="mt-2 inline-block text-xs font-medium text-cyan-300 hover:underline">Manage departments</a>
                @endif
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg xl:col-span-1">
                <h2 class="text-lg font-semibold text-white">Quick links</h2>
                <p class="mt-1 text-sm text-slate-400">Jump to related HR configuration screens.</p>
                <ul class="mt-4 space-y-2 text-sm">
                    @if ($canViewContractTypes)
                        <li>
                            <a href="{{ route('admin.hr.contract-types.index') }}" class="flex items-center justify-between rounded-xl border border-white/10 px-3 py-2 text-slate-200 hover:bg-white/10">
                                <span>Contract types</span>
                                <span class="text-cyan-300">→</span>
                            </a>
                        </li>
                    @endif
                    @if ($canViewDepartments)
                        <li>
                            <a href="{{ route('admin.hr.departments.index') }}" class="flex items-center justify-between rounded-xl border border-white/10 px-3 py-2 text-slate-200 hover:bg-white/10">
                                <span>Departments</span>
                                <span class="text-cyan-300">→</span>
                            </a>
                        </li>
                    @endif
                    @if ($admin?->can('hr.positions.view'))
                        <li>
                            <a href="{{ route('admin.hr.positions.index') }}" class="flex items-center justify-between rounded-xl border border-white/10 px-3 py-2 text-slate-200 hover:bg-white/10">
                                <span>Positions</span>
                                <span class="text-cyan-300">→</span>
                            </a>
                        </li>
                    @endif
                    @if ($admin?->can('hr.leave-balances.view'))
                        <li>
                            <a href="{{ route('admin.hr.leave.balances.index') }}" class="flex items-center justify-between rounded-xl border border-white/10 px-3 py-2 text-slate-200 hover:bg-white/10">
                                <span>Leave balances</span>
                                <span class="text-cyan-300">→</span>
                            </a>
                        </li>
                    @endif
                    @if ($admin?->can('hr.leave.view'))
                        <li>
                            <a href="{{ route('admin.hr.leave.applications.index') }}" class="flex items-center justify-between rounded-xl border border-white/10 px-3 py-2 text-slate-200 hover:bg-white/10">
                                <span>Pending leave</span>
                                <span class="text-cyan-300">→</span>
                            </a>
                        </li>
                    @endif
                </ul>

                @if ($canManageSettings)
                    <div class="mt-6 rounded-2xl border border-amber-400/20 bg-amber-500/5 px-4 py-3 text-xs text-amber-100/90">
                        <p class="font-semibold text-amber-200">Development defaults</p>
                        <p class="mt-1 text-amber-100/80">Refresh seeded leave types and contract types with:</p>
                        <code class="mt-2 block rounded-lg bg-black/20 px-2 py-1 font-mono text-[11px] text-amber-50">php artisan db:seed --class=HrSeeder</code>
                    </div>
                @endif
            </div>

            <div class="space-y-6 xl:col-span-2">
                <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Leave types</h2>
                            <p class="text-sm text-slate-400">Used when applying for leave and calculating accrual.</p>
                        </div>
                    </div>

                    <div class="admin-data-table">
                        <div class="admin-data-table__scroll">
                            <table class="min-w-full w-full">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Code</th>
                                        <th scope="col">Paid</th>
                                        <th scope="col">Accrual</th>
                                        <th scope="col">Attachment</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($leaveTypes as $type)
                                        <tr>
                                            <td class="font-medium text-white">{{ $type->name }}</td>
                                            <td><span class="font-mono text-xs text-slate-300">{{ $type->code }}</span></td>
                                            <td>{{ $type->is_paid ? 'Yes' : 'No' }}</td>
                                            <td>{{ $type->accrual_based ? 'Yes' : 'No' }}</td>
                                            <td>{{ $type->requires_attachment ? 'Required' : '—' }}</td>
                                            <td>
                                                <span class="text-sm font-medium {{ $type->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-8 text-center text-slate-400">No leave types configured.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Contract types</h2>
                            <p class="text-sm text-slate-400">Permanent, fixed-term, and other employment contract categories.</p>
                        </div>
                        @if ($canManageContractTypes)
                            <a
                                href="{{ route('admin.hr.contract-types.create') }}"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-xs font-semibold text-cyan-200 hover:bg-cyan-500/20"
                            >
                                Add contract type
                            </a>
                        @elseif ($canViewContractTypes)
                            <a href="{{ route('admin.hr.contract-types.index') }}" class="text-xs font-semibold text-cyan-300 hover:underline">View all</a>
                        @endif
                    </div>

                    <div class="admin-data-table">
                        <div class="admin-data-table__scroll">
                            <table class="min-w-full w-full">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Code</th>
                                        <th scope="col">Permanent</th>
                                        <th scope="col">End date</th>
                                        <th scope="col">Leave / month</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($contractTypes as $type)
                                        <tr>
                                            <td class="font-medium text-white">{{ $type->name }}</td>
                                            <td><span class="font-mono text-xs text-slate-300">{{ $type->code }}</span></td>
                                            <td>{{ $type->is_permanent ? 'Yes' : 'No' }}</td>
                                            <td>{{ $type->has_end_date ? 'Required' : '—' }}</td>
                                            <td>{{ $type->leave_days_per_month !== null ? number_format((float) $type->leave_days_per_month, 2) : '—' }}</td>
                                            <td>
                                                <span class="text-sm font-medium {{ $type->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-8 text-center text-slate-400">No contract types configured.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h2 class="text-lg font-semibold text-white">Scheduled jobs</h2>
                    <p class="mt-1 text-sm text-slate-400">Monthly leave accrual for eligible active contracts.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                        <code class="font-mono text-sm text-cyan-100">php artisan hr:accrue-leave</code>
                        <span class="text-xs text-slate-500">Run via scheduler or manually each month</span>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
