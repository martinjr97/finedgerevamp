@php
    use App\Models\ContractTypeLeaveRule;

    $isEdit = $contractType !== null;
    $rulesByLeaveType = $isEdit ? $contractType->leaveRules->keyBy('leave_type_id') : collect();
    $formAction = $isEdit
        ? route('admin.hr.contract-types.update', $contractType)
        : route('admin.hr.contract-types.store');
    $pageTitle = $isEdit ? 'Edit contract type' : 'Add contract type';
    $submitLabel = $isEdit ? 'Save changes' : 'Create contract type';

    $defaultGenderForCode = function (string $code): string {
        return match ($code) {
            'maternity' => ContractTypeLeaveRule::GENDER_FEMALE,
            'paternity' => ContractTypeLeaveRule::GENDER_MALE,
            default => ContractTypeLeaveRule::GENDER_ALL,
        };
    };
@endphp

<form method="POST" action="{{ $formAction }}" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @include('partials.admin.page-header', [
        'title' => $pageTitle,
        'description' => 'Contract terms and leave accrual rules per leave type.',
        'buttons' => [[
            'action' => 'back',
            'text' => $isEdit ? 'View contract type' : 'All contract types',
            'href' => $isEdit ? route('admin.hr.contract-types.show', $contractType) : route('admin.hr.contract-types.index'),
        ]],
    ])

    <div class="grid gap-6 lg:grid-cols-2 lg:items-stretch">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 h-full">
            <h2 class="text-lg font-semibold text-white">Basic details</h2>
            <div class="grid gap-4">
                <div>
                    <label for="contract_type_code" class="text-sm font-medium text-slate-200">Code <span class="text-rose-400">*</span></label>
                    <input id="contract_type_code" name="code" required value="{{ old('code', $contractType?->code) }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 font-mono text-white @error('code') border-rose-400 @enderror">
                    @error('code')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contract_type_name" class="text-sm font-medium text-slate-200">Name <span class="text-rose-400">*</span></label>
                    <input id="contract_type_name" name="name" required value="{{ old('name', $contractType?->name) }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white @error('name') border-rose-400 @enderror">
                    @error('name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="contract_type_description" class="text-sm font-medium text-slate-200">Description</label>
                    <textarea id="contract_type_description" name="description" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white">{{ old('description', $contractType?->description) }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 h-full">
            <h2 class="text-lg font-semibold text-white">Contract terms</h2>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="is_permanent" value="0">
                <input type="checkbox" name="is_permanent" value="1" @checked(old('is_permanent', $contractType?->is_permanent)) class="rounded border-white/20 bg-white/10 text-cyan-500">
                Permanent contract
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="has_end_date" value="0">
                <input type="checkbox" name="has_end_date" value="1" @checked(old('has_end_date', $contractType?->has_end_date ?? true)) class="rounded border-white/20 bg-white/10 text-cyan-500">
                Requires end date
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="renewable" value="0">
                <input type="checkbox" name="renewable" value="1" @checked(old('renewable', $contractType?->renewable)) class="rounded border-white/20 bg-white/10 text-cyan-500">
                Renewable
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="leave_accrual_enabled" value="0">
                <input type="checkbox" name="leave_accrual_enabled" value="1" @checked(old('leave_accrual_enabled', $contractType?->leave_accrual_enabled ?? true)) class="rounded border-white/20 bg-white/10 text-cyan-500">
                Leave accrual enabled (contract level)
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $contractType?->is_active ?? true)) class="rounded border-white/20 bg-white/10 text-cyan-500">
                Active
            </label>
            </div>
            <div class="grid gap-4">
                <div>
                    <label for="default_duration_months" class="text-sm font-medium text-slate-200">Default duration (months)</label>
                    <input id="default_duration_months" type="number" min="1" name="default_duration_months" value="{{ old('default_duration_months', $contractType?->default_duration_months) }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white">
                </div>
                <div>
                    <label for="probation_months" class="text-sm font-medium text-slate-200">Probation (months)</label>
                    <input id="probation_months" type="number" min="0" name="probation_months" value="{{ old('probation_months', $contractType?->probation_months) }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white">
                </div>
                <div>
                    <label for="leave_days_per_month" class="text-sm font-medium text-slate-200">Fallback leave days / month</label>
                    <input id="leave_days_per_month" type="number" step="0.01" min="0" name="leave_days_per_month" value="{{ old('leave_days_per_month', $contractType?->leave_days_per_month) }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white">
                    <p class="mt-1 text-xs text-slate-500">Legacy default; per-type rules below take precedence.</p>
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-white">Leave types</h2>
            <p class="mt-1 text-sm text-slate-400">
                For each leave type offered under this contract, set whether leave accrues monthly, how many days accrue, and which employees may use it.
            </p>
        </div>

        @if ($leaveTypes->isEmpty())
            <p class="text-sm text-slate-400">No active leave types found. Add leave types in HR settings first.</p>
        @else
            <div class="admin-data-table overflow-x-auto">
                <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col" class="w-10">Offer</th>
                            <th scope="col">Leave type</th>
                            <th scope="col">Accrues monthly</th>
                            <th scope="col">Days / month</th>
                            <th scope="col">Applies to</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaveTypes as $leaveType)
                            @php
                                $rule = $rulesByLeaveType->get($leaveType->id);
                                $rowKey = $leaveType->id;
                                $enabledDefault = $rule !== null || ! $isEdit;
                                $enabled = filter_var(old("leave_rules.{$rowKey}.enabled", $enabledDefault), FILTER_VALIDATE_BOOLEAN);
                                $accrues = filter_var(
                                    old("leave_rules.{$rowKey}.requires_accrual", $rule?->requires_accrual ?? $leaveType->accrual_based),
                                    FILTER_VALIDATE_BOOLEAN
                                );
                                $daysDefault = $rule?->days_per_month
                                    ?? ($leaveType->code === 'annual' ? ($contractType?->leave_days_per_month ?? 2) : 0);
                                $days = old("leave_rules.{$rowKey}.days_per_month", $daysDefault);
                                $gender = old(
                                    "leave_rules.{$rowKey}.applicable_gender",
                                    $rule?->applicable_gender ?? $defaultGenderForCode($leaveType->code)
                                );
                            @endphp
                            <tr>
                                <td>
                                    <input type="hidden" name="leave_rules[{{ $rowKey }}][enabled]" value="0">
                                    <input
                                        type="checkbox"
                                        name="leave_rules[{{ $rowKey }}][enabled]"
                                        value="1"
                                        @checked($enabled)
                                        class="rounded border-white/20 bg-white/10 text-cyan-500"
                                        title="Include this leave type on this contract"
                                    >
                                </td>
                                <td>
                                    <input type="hidden" name="leave_rules[{{ $rowKey }}][leave_type_id]" value="{{ $leaveType->id }}">
                                    <span class="font-medium text-white">{{ $leaveType->name }}</span>
                                    <span class="block font-mono text-xs text-slate-500">{{ $leaveType->code }}</span>
                                </td>
                                <td>
                                    <input type="hidden" name="leave_rules[{{ $rowKey }}][requires_accrual]" value="0">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                        <input
                                            type="checkbox"
                                            name="leave_rules[{{ $rowKey }}][requires_accrual]"
                                            value="1"
                                            @checked($accrues)
                                            class="rounded border-white/20 bg-white/10 text-cyan-500"
                                        >
                                        Yes
                                    </label>
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="leave_rules[{{ $rowKey }}][days_per_month]"
                                        value="{{ $days }}"
                                        class="w-28 rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white"
                                    >
                                </td>
                                <td>
                                    <select
                                        name="leave_rules[{{ $rowKey }}][applicable_gender]"
                                        class="min-w-[10rem] rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white"
                                    >
                                        <option value="{{ ContractTypeLeaveRule::GENDER_ALL }}" @selected($gender === ContractTypeLeaveRule::GENDER_ALL)>Male &amp; female</option>
                                        <option value="{{ ContractTypeLeaveRule::GENDER_MALE }}" @selected($gender === ContractTypeLeaveRule::GENDER_MALE)>Male only</option>
                                        <option value="{{ ContractTypeLeaveRule::GENDER_FEMALE }}" @selected($gender === ContractTypeLeaveRule::GENDER_FEMALE)>Female only</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <a href="{{ $isEdit ? route('admin.hr.contract-types.show', $contractType) : route('admin.hr.contract-types.index') }}" class="rounded-xl border border-white/10 px-5 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">
                Cancel
            </a>
            <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">
                {{ $submitLabel }}
            </button>
        </div>
    </section>
</form>
