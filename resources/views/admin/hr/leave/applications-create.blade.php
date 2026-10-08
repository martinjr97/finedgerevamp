@extends('layouts.admin')

@section('title', 'Apply for Leave | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Leave Application',
            'description' => 'Submit leave on behalf of an employee. Available balance is loaded when you choose employee and leave type.',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back to pending',
                'href' => route('admin.hr.leave.applications.index'),
            ]],
        ])

        <div class="mx-auto w-full max-w-2xl">
            <form
                method="POST"
                action="{{ route('admin.hr.leave.applications.store') }}"
                x-data="leaveApplicationForm({
                    leaveTypes: @js($leaveTypesForForm),
                    balancePreviewUrl: @js($balancePreviewUrl),
                    canApproveImmediately: @js($canApproveImmediately),
                })"
                class="space-y-5 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg"
                @submit="if (submitDisabled) { $event.preventDefault(); if (requiresBalanceCheck && hasNoAvailableDays && window.Swal) { alertZeroBalance(leaveTypeName || selectedType?.name, availableBalance ?? 0); } }"
            >
                @csrf

                @if ($errors->any())
                    <div class="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label for="employee_id" class="text-sm text-slate-300">Employee <span class="text-rose-400">*</span></label>
                    <select
                        id="employee_id"
                        name="employee_id"
                        required
                        x-model="employeeId"
                        @change="onEmployeeChange()"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                    >
                        <option value="">Select employee</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->full_name }} ({{ $employee->employee_number }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="leave_type_id" class="text-sm text-slate-300">Leave type <span class="text-rose-400">*</span></label>
                    <select
                        id="leave_type_id"
                        name="leave_type_id"
                        required
                        x-model="leaveTypeId"
                        @change="onLeaveTypeChange()"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                    >
                        <option value="">Select type</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div
                    x-show="leaveTypeId"
                    x-cloak
                    class="rounded-2xl border px-4 py-3 text-sm"
                    :class="balanceBannerClass"
                >
                    <template x-if="!employeeId">
                        <p class="text-amber-100">Select an employee to see how many days are available for this leave type.</p>
                    </template>
                    <template x-if="employeeId && balanceLoading">
                        <p class="text-slate-300">Loading available balance…</p>
                    </template>
                    <template x-if="employeeId && !balanceLoading && balanceError">
                        <p class="text-rose-200" x-text="balanceError"></p>
                    </template>
                    <template x-if="employeeId && !balanceLoading && !balanceError && availableBalance !== null">
                        <div class="space-y-1">
                            <p class="text-slate-200">
                                <span class="text-slate-400">Available for</span>
                                <span class="font-semibold text-white" x-text="leaveTypeName || selectedType?.name || 'selected leave'"></span>:
                                <span class="text-lg font-bold" :class="hasNoAvailableDays ? 'text-rose-300' : 'text-emerald-300'" x-text="availableBalance.toFixed(2) + ' day(s)'"></span>
                            </p>
                            <p x-show="requiresBalanceCheck && hasNoAvailableDays" class="text-rose-100">
                                No days available for this leave type. Choose another type or adjust balances before applying.
                            </p>
                        </div>
                    </template>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="start_date" class="text-sm text-slate-300">Start date <span class="text-rose-400">*</span></label>
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            required
                            value="{{ old('start_date') }}"
                            x-model="startDate"
                            @change="updateDays(); refreshBalance({ alertOnZero: false })"
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                        >
                    </div>
                    <div>
                        <label for="end_date" class="text-sm text-slate-300">End date <span class="text-rose-400">*</span></label>
                        <input
                            type="date"
                            id="end_date"
                            name="end_date"
                            required
                            value="{{ old('end_date') }}"
                            x-model="endDate"
                            @change="updateDays(); refreshBalance({ alertOnZero: false })"
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                        >
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm" x-show="employeeId && leaveTypeId && startDate && endDate" x-cloak>
                    <p class="text-slate-300">
                        Days requested:
                        <span class="font-semibold text-white" x-text="daysRequested !== null ? daysRequested.toFixed(2) : '—'"></span>
                    </p>
                    <p x-show="requiresBalanceCheck && !hasNoAvailableDays && daysRequested !== null && availableBalance !== null && !balanceOk" class="mt-2 text-rose-300">
                        Requested days exceed available balance. Reduce the date range or adjust balances before submitting.
                    </p>
                </div>

                <div>
                    <label for="reason" class="text-sm text-slate-300">Reason</label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="3"
                        placeholder="Optional notes for approvers"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                    >{{ old('reason') }}</textarea>
                </div>

                @if ($canApproveImmediately)
                    <div class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 px-4 py-3 space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                name="approve_now"
                                value="1"
                                class="mt-1 rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-500/40"
                                @checked(old('approve_now'))
                            >
                            <span class="text-sm text-slate-200">
                                <span class="font-medium text-white">Approve immediately</span>
                                <span class="block text-slate-400">You have both apply and approve permissions. Leave the box unchecked to send for another approver.</span>
                            </span>
                        </label>
                        <div>
                            <label for="approver_comments" class="text-sm text-slate-300">Approval comments</label>
                            <input
                                type="text"
                                id="approver_comments"
                                name="approver_comments"
                                value="{{ old('approver_comments') }}"
                                class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white"
                                placeholder="Optional if approving now"
                            >
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <a href="{{ route('admin.hr.leave.applications.index') }}" class="rounded-2xl border border-white/10 px-5 py-2.5 text-sm text-slate-300 hover:bg-white/10">Cancel</a>
                    <button
                        type="submit"
                        class="btn-primary rounded-2xl px-6 py-2.5 text-sm font-semibold shadow-lg disabled:opacity-50"
                        :disabled="submitDisabled"
                    >
                        Submit application
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function leaveApplicationForm(config) {
        return {
            leaveTypes: config.leaveTypes || [],
            balancePreviewUrl: config.balancePreviewUrl,
            employeeId: @js(old('employee_id', '')),
            leaveTypeId: @js(old('leave_type_id', '')),
            startDate: @js(old('start_date', '')),
            endDate: @js(old('end_date', '')),
            daysRequested: null,
            availableBalance: null,
            leaveTypeName: null,
            balanceLoading: false,
            balanceError: null,
            get selectedType() {
                return this.leaveTypes.find(t => String(t.id) === String(this.leaveTypeId));
            },
            get selectedTypeAccrual() {
                return this.selectedType?.accrual_based ?? false;
            },
            get requiresBalanceCheck() {
                return this.selectedType?.requires_balance_check ?? true;
            },
            get hasNoAvailableDays() {
                return this.availableBalance !== null && this.availableBalance <= 0.0001;
            },
            get balanceBannerClass() {
                if (!this.employeeId || this.balanceLoading) {
                    return 'border-white/10 bg-white/5';
                }
                if (this.balanceError) {
                    return 'border-rose-400/40 bg-rose-500/10';
                }
                if (this.hasNoAvailableDays && this.requiresBalanceCheck) {
                    return 'border-rose-400/40 bg-rose-500/10';
                }
                return 'border-emerald-400/30 bg-emerald-500/5';
            },
            get balanceOk() {
                if (!this.requiresBalanceCheck || this.availableBalance === null) {
                    return true;
                }
                if (this.hasNoAvailableDays) {
                    return false;
                }
                if (this.daysRequested === null) {
                    return true;
                }
                return this.availableBalance + 0.0001 >= this.daysRequested;
            },
            get submitDisabled() {
                if (!this.requiresBalanceCheck) {
                    return false;
                }
                if (this.balanceLoading) {
                    return true;
                }
                if (this.hasNoAvailableDays) {
                    return true;
                }
                if (this.daysRequested !== null && this.availableBalance !== null && !this.balanceOk) {
                    return true;
                }
                return false;
            },
            inclusiveDays(start, end) {
                if (!start || !end) return null;
                const s = new Date(start + 'T12:00:00');
                const e = new Date(end + 'T12:00:00');
                if (e < s) return null;
                return Math.round((e - s) / 86400000) + 1;
            },
            updateDays() {
                this.daysRequested = this.inclusiveDays(this.startDate, this.endDate);
            },
            onEmployeeChange() {
                this.refreshBalance({ alertOnZero: !!this.leaveTypeId });
            },
            onLeaveTypeChange() {
                this.refreshBalance({ alertOnZero: true });
            },
            alertZeroBalance(typeName, available) {
                if (!window.Swal) {
                    return;
                }
                const days = Number(available).toFixed(2);
                Swal.fire({
                    icon: 'warning',
                    title: 'No leave available',
                    html: `This employee has <strong>${days}</strong> day(s) available for <strong>${typeName}</strong>.<br><br>Choose a different leave type or add a balance adjustment before continuing.`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#06b6d4',
                });
            },
            async refreshBalance(options = {}) {
                const alertOnZero = options.alertOnZero === true;
                this.updateDays();
                this.balanceError = null;

                if (!this.leaveTypeId) {
                    this.availableBalance = null;
                    this.leaveTypeName = null;
                    return;
                }

                if (!this.employeeId) {
                    this.availableBalance = null;
                    this.leaveTypeName = this.selectedType?.name ?? null;
                    return;
                }

                this.balanceLoading = true;
                try {
                    const url = new URL(this.balancePreviewUrl, window.location.origin);
                    url.searchParams.set('employee_id', this.employeeId);
                    url.searchParams.set('leave_type_id', this.leaveTypeId);
                    const res = await fetch(url.toString(), {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        throw new Error('Could not load leave balance.');
                    }
                    const data = await res.json();
                    this.availableBalance = parseFloat(data.available);
                    this.leaveTypeName = data.leave_type_name || this.selectedType?.name || null;

                    if (
                        alertOnZero
                        && data.requires_balance_check
                        && this.availableBalance <= 0.0001
                    ) {
                        this.alertZeroBalance(this.leaveTypeName, this.availableBalance);
                    }
                } catch (e) {
                    this.availableBalance = null;
                    this.balanceError = e.message || 'Could not load leave balance.';
                } finally {
                    this.balanceLoading = false;
                }
            },
            init() {
                this.updateDays();
                if (this.employeeId && this.leaveTypeId) {
                    this.refreshBalance({ alertOnZero: false });
                }
            },
        };
    }
</script>
@endpush
