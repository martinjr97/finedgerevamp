@extends('layouts.admin')

@section('title', 'Leave Balances | HR | '.config('app.system_name'))

@section('content')
    @php
        $canAdjust = auth('admin')->user()?->can('hr.leave-balances.adjust') ?? false;
        $openAdjustmentModal = $canAdjust && $errors->hasAny(['leave_type_id', 'days', 'description', 'employee_id']);
        $initialAdjustEmployeeId = (int) old('employee_id', $filteredEmployee?->id ?? 0) ?: null;
    @endphp

    <div
        class="space-y-8"
        x-data="{
            adjustmentModalOpen: @js($openAdjustmentModal),
            adjustmentEmployeeId: @js($initialAdjustEmployeeId),
            returnEmployeeId: @js($filterEmployeeId),
            openAdjustment(employeeId) {
                this.adjustmentEmployeeId = employeeId;
                this.adjustmentModalOpen = true;
            }
        }"
    >
        @include('partials.admin.page-header', [
            'title' => 'Leave Balances',
            'description' => 'Available leave days for all active employees. Filter by employee for a focused view.',
        ])

        <form method="GET" class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="min-w-[14rem] flex-1 sm:max-w-md">
                    <label for="employee_id" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Filter by employee (optional)</label>
                    <select id="employee_id" name="employee_id" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                        <option value="">All employees</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}" @selected($filterEmployeeId === $emp->id)>{{ $emp->full_name }} ({{ $emp->employee_number ?? '—' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-xl border border-cyan-500/40 bg-cyan-500/20 px-4 py-2 text-sm font-medium text-cyan-200 hover:bg-cyan-500/30">Apply filter</button>
                    @if ($filterEmployeeId)
                        <a href="{{ route('admin.hr.leave.balances.index') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/10">Clear filter</a>
                        <a href="{{ route('admin.hr.employees.show', $filteredEmployee) }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/10">View employee</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($filteredEmployee)
            <p class="text-sm text-slate-400">
                Showing balances for <span class="font-medium text-white">{{ $filteredEmployee->full_name }}</span>
                · {{ $filteredEmployee->hrDepartment?->name ?? 'No department' }}
            </p>
        @else
            <p class="text-sm text-slate-400">
                Showing <span class="font-medium text-white">{{ number_format($displayEmployees->count()) }}</span> active employees
                · available days per leave type
            </p>
        @endif

        <div class="admin-data-table overflow-x-auto">
            <table
                data-datatable="true"
                data-datatable-per-page="25"
                data-datatable-search-placeholder="Search employees…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr>
                        <th scope="col">Employee</th>
                        <th scope="col">Department</th>
                        @foreach ($leaveTypes as $type)
                            <th scope="col" class="whitespace-nowrap" title="Available {{ $type->name }}">{{ $type->name }}</th>
                        @endforeach
                        @if ($canAdjust)
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($displayEmployees as $emp)
                        @php
                            $rowBalances = $balanceMatrix[$emp->id] ?? [];
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.hr.employees.show', $emp) }}" class="font-medium text-cyan-200 hover:underline">
                                    {{ $emp->full_name }}
                                </a>
                                <span class="block font-mono text-xs text-slate-500">{{ $emp->employee_number ?? '—' }}</span>
                            </td>
                            <td>{{ $emp->hrDepartment?->name ?? '—' }}</td>
                            @foreach ($leaveTypes as $type)
                                @php
                                    $cell = $rowBalances[$type->code] ?? ['available' => 0];
                                    $available = (float) ($cell['available'] ?? 0);
                                @endphp
                                <td>
                                    <span class="text-sm font-semibold {{ $available > 0 ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ number_format($available, 2) }}
                                    </span>
                                </td>
                            @endforeach
                            @if ($canAdjust)
                                <td>
                                    <button
                                        type="button"
                                        @click="openAdjustment({{ $emp->id }})"
                                        class="inline-flex items-center rounded-lg border border-emerald-400/50 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-500/20 transition"
                                    >
                                        Adjust
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + $leaveTypes->count() + ($canAdjust ? 1 : 0) }}" class="py-8 text-center text-slate-400">
                                No active employees to display.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($filteredEmployee && $employeeDetailBalances !== [])
            <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Balance detail</h2>
                        <p class="text-sm text-slate-400">Accrued, taken, and adjustments for {{ $filteredEmployee->full_name }}.</p>
                    </div>
                    @if ($canAdjust)
                        <button type="button" @click="openAdjustment({{ $filteredEmployee->id }})" class="btn-primary shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold">
                            Manual adjustment
                        </button>
                    @endif
                </div>
                <div class="admin-data-table">
                    <table class="min-w-full w-full">
                        <thead>
                            <tr>
                                <th scope="col">Leave type</th>
                                <th scope="col">Accrued</th>
                                <th scope="col">Taken</th>
                                <th scope="col">Adjustments</th>
                                <th scope="col">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employeeDetailBalances as $balance)
                                <tr>
                                    <td class="font-medium text-white">{{ $balance['name'] }}</td>
                                    <td>{{ number_format((float) $balance['accrued'], 2) }}</td>
                                    <td>{{ number_format((float) $balance['taken'], 2) }}</td>
                                    <td>{{ number_format((float) $balance['adjustments'], 2) }}</td>
                                    <td>
                                        <span class="text-sm font-semibold {{ $balance['available'] > 0 ? 'text-emerald-400' : 'text-slate-400' }}">
                                            {{ number_format((float) $balance['available'], 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($canAdjust)
            @include('admin.hr.leave.partials.balance-adjustment-modal', [
                'employees' => $employees,
                'leaveTypes' => $leaveTypes,
            ])
        @endif
    </div>
@endsection
