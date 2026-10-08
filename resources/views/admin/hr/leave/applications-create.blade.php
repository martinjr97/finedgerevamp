@extends('layouts.admin')

@section('title', 'Apply for Leave | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Leave Application',
            'description' => 'Submit leave on behalf of an employee. Accrual-based leave is checked against available balance.',
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
                        @change="refreshBalance()"
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
                        @change="refreshBalance()"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                    >
                        <option value="">Select type</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
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
                            @change="updateDays()"
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
                            @change="updateDays()"
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-white"
                        >
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm" x-show="employeeId && leaveTypeId" x-cloak>
                    <p class="text-slate-300">
                        Days requested:
                        <span class="font-semibold text-white" x-text="daysRequested !== null ? daysRequested.toFixed(2) : '—'"></span>
                    </p>
                    <template x-if="selectedTypeAccrual">
                        <p class="mt-1 text-slate-400">
                            Available balance:
                            <span class="font-medium" :class="balanceOk ? 'text-emerald-300' : 'text-rose-300'" x-text="balanceLoading ? 'Loading…' : (availableBalance !== null ? availableBalance.toFixed(2) + ' day(s)' : '—')"></span>
                        </p>
                    </template>
                    <p x-show="selectedTypeAccrual && daysRequested !== null && availableBalance !== null && !balanceOk" class="mt-2 text-rose-300">
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
                        class="rounded-2xl bg-gradient-to-r from-cyan-500 to-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg disabled:opacity-50"
                        :disabled="selectedTypeAccrual && daysRequested !== null && availableBalance !== null && !balanceOk"
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
            balanceLoading: false,
            get selectedType() {
                return this.leaveTypes.find(t => String(t.id) === String(this.leaveTypeId));
            },
            get selectedTypeAccrual() {
                return this.selectedType?.accrual_based ?? false;
            },
            get balanceOk() {
                if (!this.selectedTypeAccrual || this.daysRequested === null || this.availableBalance === null) {
                    return true;
                }
                return this.availableBalance + 0.0001 >= this.daysRequested;
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
            async refreshBalance() {
                this.updateDays();
                if (!this.employeeId || !this.leaveTypeId) {
                    this.availableBalance = null;
                    return;
                }
                this.balanceLoading = true;
                try {
                    const url = new URL(this.balancePreviewUrl, window.location.origin);
                    url.searchParams.set('employee_id', this.employeeId);
                    url.searchParams.set('leave_type_id', this.leaveTypeId);
                    const res = await fetch(url.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) throw new Error('Balance lookup failed');
                    const data = await res.json();
                    this.availableBalance = data.accrual_based ? parseFloat(data.available) : null;
                } catch (e) {
                    this.availableBalance = null;
                } finally {
                    this.balanceLoading = false;
                }
            },
            init() {
                if (this.employeeId && this.leaveTypeId) {
                    this.refreshBalance();
                } else {
                    this.updateDays();
                }
            },
        };
    }
</script>
@endpush
