@extends('layouts.admin')

@section('title', $employee->full_name.' | HR | '.config('app.system_name'))

@section('content')
    @php
        $statusClass = match ($employee->employment_status) {
            'active' => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300',
            'terminated', 'deceased' => 'border-rose-400/40 bg-rose-500/10 text-rose-300',
            'suspended' => 'border-amber-400/40 bg-amber-500/10 text-amber-300',
            default => 'border-white/20 bg-white/5 text-slate-300',
        };
        $tabs = ['overview' => 'Overview', 'employment' => 'Employment', 'contract' => 'Contracts'];
        if ($canViewCompensation) {
            $tabs['compensation'] = 'Compensation';
        }
        if ($canViewBank) {
            $tabs['bank'] = 'Payment';
        }
        $tabs['kin'] = 'Next of kin';
        $tabs['dependants'] = 'Dependants';
        $tabs['leave'] = 'Leave';
        $paymentErrorKeys = [
            'account_name', 'account_number', 'bank_name', 'financial_institution_id',
            'branch_name', 'branch_code', 'is_primary', 'mobile_money_provider',
            'mobile_account_name', 'mobile_number', 'payout_method',
        ];
        $initialTab = session('hr_employee_tab', 'overview');
        if ($errors->hasAny(['basic_pay', 'effective_from', 'currency'])) {
            $initialTab = 'compensation';
        } elseif ($errors->hasAny($paymentErrorKeys)) {
            $initialTab = 'bank';
        } elseif (old('_form') === 'next_of_kin' && $errors->any()) {
            $initialTab = 'kin';
        } elseif (old('_form') === 'dependant' && $errors->any()) {
            $initialTab = 'dependants';
        }
        $openCompensationModal = $errors->hasAny(['basic_pay', 'effective_from', 'currency']);
        $openPaymentModal = $errors->hasAny($paymentErrorKeys);
        $openKinModal = old('_form') === 'next_of_kin' && $errors->any();
        $openDependantModal = old('_form') === 'dependant' && $errors->any();
        $paymentPayoutMethod = old('payout_method', 'bank');
    @endphp

    <div class="space-y-8" x-data="{ tab: @js($initialTab), compensationModalOpen: @js($openCompensationModal), paymentModalOpen: @js($openPaymentModal), kinModalOpen: @js($openKinModal), dependantModalOpen: @js($openDependantModal), payoutMethod: @js($paymentPayoutMethod) }">
        @include('partials.admin.page-header', [
            'title' => $employee->full_name,
            'description' => 'Employee profile, contracts, compensation, and related records.',
            'buttons' => array_filter([
                [
                    'action' => 'back',
                    'text' => 'All employees',
                    'href' => route('admin.hr.employees.index'),
                ],
                auth('admin')->user()?->can('hr.employees.update') ? [
                    'action' => 'edit',
                    'text' => 'Edit employee',
                    'href' => route('admin.hr.employees.edit', $employee),
                ] : null,
            ]),
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-cyan-500/15 text-lg font-bold text-cyan-200 ring-1 ring-cyan-400/30">
                        {{ strtoupper(substr($employee->first_name, 0, 1).substr($employee->last_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-semibold text-white">{{ $employee->full_name }}</h2>
                            @if ($employee->title)
                                <span class="text-sm text-slate-400">{{ $employee->title }}</span>
                            @endif
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize {{ $statusClass }}">
                                {{ $employee->employment_status }}
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-sm text-slate-400">{{ $employee->employee_number ?? 'No employee number' }}</p>
                        <p class="mt-2 text-sm text-slate-300">
                            {{ $employee->position?->name ?? 'No position' }}
                            ·
                            {{ $employee->hrDepartment?->name ?? 'No department' }}
                        </p>
                    </div>
                </div>
                <dl class="grid min-w-[14rem] gap-3 text-sm sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Active contract</dt>
                        <dd class="mt-1 font-medium text-white">{{ $employee->activeContract?->contractType?->name ?? '—' }}</dd>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Work location</dt>
                        <dd class="mt-1 font-medium text-white">{{ $employee->branch?->name ?? $employee->work_location ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-white/10 pb-1">
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'border-cyan-400/60 bg-cyan-500/20 text-cyan-100'
                        : 'border-transparent text-slate-400 hover:text-white hover:bg-white/5'"
                    class="rounded-t-xl border-b-2 px-4 py-2 text-sm font-semibold transition"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Overview --}}
        <section x-show="tab === 'overview'" x-cloak class="space-y-6">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h3 class="text-lg font-semibold text-white">Personal details</h3>
                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Gender', 'value' => $employee->gender ? ucfirst($employee->gender) : null])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Date of birth', 'value' => $employee->date_of_birth?->format('d M Y')])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'National ID / NRC', 'value' => $employee->national_id])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Nationality', 'value' => $employee->nationality])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Marital status', 'value' => $employee->marital_status ? ucfirst($employee->marital_status) : null])
                </dl>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h3 class="text-lg font-semibold text-white">Contact</h3>
                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Work email', 'value' => $employee->email])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Personal email', 'value' => $employee->personal_email])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Phone', 'value' => $employee->phone])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Alternative phone', 'value' => $employee->alternative_phone])
                </dl>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h3 class="text-lg font-semibold text-white">Address</h3>
                <dl class="grid gap-3 sm:grid-cols-1 lg:grid-cols-2">
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Residential address', 'value' => $employee->residential_address])
                    @include('admin.hr.employees.partials.detail-field', ['label' => 'Postal address', 'value' => $employee->postal_address])
                </dl>
            </div>
        </section>

        {{-- Employment --}}
        <section x-show="tab === 'employment'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h3 class="text-lg font-semibold text-white">Employment placement</h3>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Department', 'value' => $employee->hrDepartment?->name])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Position', 'value' => $employee->position?->name])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Reports to', 'value' => $employee->supervisor?->full_name])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Work location', 'value' => $employee->branch?->name ?? $employee->work_location])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Date joined', 'value' => $employee->date_joined?->format('d M Y')])
                @include('admin.hr.employees.partials.detail-field', ['label' => 'Employment start', 'value' => $employee->employment_start_date?->format('d M Y')])
            </dl>
            @if ($employee->hrDepartment)
                <a href="{{ route('admin.hr.departments.show', $employee->hrDepartment) }}" class="inline-block text-sm font-medium text-cyan-300 hover:underline">View department</a>
            @endif
        </section>

        {{-- Contracts --}}
        <section x-show="tab === 'contract'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-white">Contract history</h3>
                @can('hr.contracts.manage')
                    <a href="{{ route('admin.hr.contracts.create', ['employee_id' => $employee->id]) }}" class="text-xs font-semibold text-cyan-300 hover:underline">Add contract</a>
                @endcan
            </div>
            <div class="admin-data-table">
                <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col">Number</th>
                            <th scope="col">Period</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->contracts as $contract)
                            @php
                                $cStatus = match ($contract->status) {
                                    'active' => 'text-emerald-400',
                                    'draft' => 'text-amber-300',
                                    default => 'text-slate-400',
                                };
                            @endphp
                            <tr>
                                <td class="font-medium text-white">{{ $contract->contractType?->name ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $contract->contract_number ?? '—' }}</td>
                                <td>{{ $contract->start_date->format('d M Y') }} – {{ $contract->end_date?->format('d M Y') ?? 'Open' }}</td>
                                <td><span class="text-sm font-medium capitalize {{ $cStatus }}">{{ $contract->status }}</span></td>
                                <td>
                                    <a href="{{ route('admin.hr.contracts.show', $contract) }}" class="inline-flex rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">No contracts recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Compensation --}}
        @if ($canViewCompensation)
            <section x-show="tab === 'compensation'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Compensation</h3>
                        <p class="mt-1 text-sm text-slate-400">Basic salary and historical pay changes.</p>
                    </div>
                    @can('hr.employee.compensation.manage')
                        <button type="button" @click="compensationModalOpen = true" class="btn-primary shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold">
                            Update basic pay
                        </button>
                    @endcan
                </div>

                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @include('admin.hr.employees.partials.detail-field', [
                        'label' => 'Current basic pay (ZMW)',
                        'value' => $employee->currentCompensation
                            ? number_format((float) $employee->currentCompensation->basic_pay, 2)
                            : '—',
                    ])
                    @include('admin.hr.employees.partials.detail-field', [
                        'label' => 'Effective from',
                        'value' => $employee->currentCompensation?->effective_from?->format('d M Y') ?? '—',
                    ])
                    @include('admin.hr.employees.partials.detail-field', [
                        'label' => 'Currency',
                        'value' => $employee->currentCompensation?->currency ?? 'ZMW',
                    ])
                </dl>

                <div>
                    <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Pay history</h4>
                    <div class="admin-data-table">
                        <table class="min-w-full w-full">
                            <thead>
                                <tr>
                                    <th scope="col">Basic pay</th>
                                    <th scope="col">Effective from</th>
                                    <th scope="col">Effective to</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($employee->compensations as $row)
                                    <tr>
                                        <td class="font-medium text-white">
                                            {{ $row->currency ?? 'ZMW' }} {{ number_format((float) $row->basic_pay, 2) }}
                                        </td>
                                        <td>{{ $row->effective_from?->format('d M Y') ?? '—' }}</td>
                                        <td>{{ $row->effective_to?->format('d M Y') ?? '—' }}</td>
                                        <td>
                                            @if ($row->is_current)
                                                <span class="inline-flex rounded-full border border-emerald-400/40 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-300">Current</span>
                                            @else
                                                <span class="text-slate-400">Past</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-slate-400">No compensation recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            @can('hr.employee.compensation.manage')
                @include('admin.hr.employees.partials.compensation-update-modal', ['employee' => $employee])
            @endcan
        @endif

        {{-- Bank / payment --}}
        @if ($canViewBank)
            <section x-show="tab === 'bank'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Payment accounts</h3>
                        <p class="mt-1 text-sm text-slate-400">Bank accounts and mobile money wallets used for payroll.</p>
                    </div>
                    @can('hr.employee.bank.manage')
                        <button type="button" @click="paymentModalOpen = true" class="btn-primary shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold">
                            Add payment account
                        </button>
                    @endcan
                </div>
                <div class="admin-data-table">
                    <table class="min-w-full w-full">
                        <thead>
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">Name / provider</th>
                                <th scope="col">Account</th>
                                <th scope="col">Primary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employee->bankAccounts as $account)
                                <tr>
                                    <td class="capitalize">{{ str_replace('_', ' ', $account->account_type ?? 'bank') }}</td>
                                    <td class="font-medium text-white">{{ $account->account_name }} @if($account->bank_name)<span class="text-slate-400 font-normal">· {{ $account->bank_name }}</span>@endif</td>
                                    <td class="font-mono text-xs">{{ $account->maskedAccountNumber() }}</td>
                                    <td>
                                        @if ($account->is_primary)
                                            <span class="inline-flex rounded-full border border-emerald-400/40 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-300">Primary</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-400">No payment details on file.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @can('hr.employee.bank.manage')
                @include('admin.hr.employees.partials.payment-account-modal', ['employee' => $employee])
            @endcan
        @endif

        {{-- Next of kin --}}
        <section x-show="tab === 'kin'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Next of kin</h3>
                    <p class="mt-1 text-sm text-slate-400">Emergency contacts on file for this employee.</p>
                </div>
                @can('hr.employees.update')
                    <button type="button" @click="kinModalOpen = true" class="btn-primary shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold">
                        Add next of kin
                    </button>
                @endcan
            </div>
            <div class="admin-data-table">
                <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Relationship</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Email</th>
                            <th scope="col">Primary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->nextOfKin as $kin)
                            <tr>
                                <td class="font-medium text-white">{{ $kin->full_name }}</td>
                                <td>{{ $kin->relationship }}</td>
                                <td>{{ $kin->phone_number ?? '—' }}</td>
                                <td>{{ $kin->email ?? '—' }}</td>
                                <td>
                                    @if ($kin->is_primary)
                                        <span class="inline-flex rounded-full border border-emerald-400/40 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-300">Primary</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">No next of kin recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('hr.employees.update')
            @include('admin.hr.employees.partials.next-of-kin-modal', ['employee' => $employee])
        @endcan

        {{-- Dependants --}}
        <section x-show="tab === 'dependants'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Dependants</h3>
                    <p class="mt-1 text-sm text-slate-400">Dependants linked to this employee for HR and benefits records.</p>
                </div>
                @can('hr.employees.update')
                    <button type="button" @click="dependantModalOpen = true" class="btn-primary shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold">
                        Add dependant
                    </button>
                @endcan
            </div>
            <div class="admin-data-table">
                <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Relationship</th>
                            <th scope="col">Date of birth</th>
                            <th scope="col">Gender</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employee->dependants as $dep)
                            <tr>
                                <td class="font-medium text-white">{{ $dep->full_name }}</td>
                                <td>{{ $dep->relationship }}</td>
                                <td>{{ $dep->date_of_birth?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $dep->gender ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-400">No dependants recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('hr.employees.update')
            @include('admin.hr.employees.partials.dependant-modal', ['employee' => $employee])
        @endcan

        {{-- Leave --}}
        <section x-show="tab === 'leave'" x-cloak class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <h3 class="text-lg font-semibold text-white">Leave balances</h3>
            <div class="admin-data-table">
                <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Leave type</th>
                            <th scope="col">Accrued</th>
                            <th scope="col">Taken</th>
                            <th scope="col">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaveBalances as $balance)
                            <tr>
                                <td class="font-medium text-white">{{ $balance['name'] }}</td>
                                <td>{{ number_format((float) $balance['accrued'], 2) }}</td>
                                <td>{{ number_format((float) $balance['taken'], 2) }}</td>
                                <td><span class="font-semibold {{ $balance['available'] > 0 ? 'text-emerald-400' : 'text-slate-400' }}">{{ number_format((float) $balance['available'], 2) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-400">No leave balance data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
