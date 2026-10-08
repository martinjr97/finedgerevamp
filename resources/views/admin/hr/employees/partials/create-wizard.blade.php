@php
    $canManageCompensation = auth('admin')->user()?->can('hr.employee.compensation.manage') ?? false;
    $canManageBank = auth('admin')->user()?->can('hr.employee.bank.manage') ?? false;
    $initialWizardStep = (int) old('_wizard_step', 1);
    if ($errors->any()) {
        if ($errors->hasAny(['contract_type_id', 'contract_start_date', 'contract_end_date', 'basic_pay', 'employee_number', 'department_id', 'position_id', 'branch_id'])) {
            $initialWizardStep = max($initialWizardStep, 2);
        }
        if ($errors->hasAny(['account_name', 'account_number', 'mobile_money_provider', 'mobile_account_name', 'mobile_number', 'payout_method'])) {
            $initialWizardStep = max($initialWizardStep, 3);
        }
    }
@endphp

<div
    x-data="employeeCreateWizard({
        contractTypes: @js($contractTypesForWizard),
        defaultStartDate: @js(old('contract_start_date', now()->toDateString())),
        positions: @js($positionsForWizard ?? []),
        positionStoreUrl: @js($positionStoreUrl ?? ''),
        canManagePositions: @js($canManagePositions ?? false),
        csrfToken: @js(csrf_token()),
    })"
    class="space-y-6"
>
    {{-- Step indicator --}}
    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide">
        <template x-for="(label, index) in ['Bio data', 'Contract & pay', 'Payment details']" :key="index">
            <div class="flex items-center gap-2">
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-full border"
                    :class="step === index + 1 ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : (step > index + 1 ? 'border-emerald-400/50 bg-emerald-500/10 text-emerald-200' : 'border-white/10 text-slate-500')"
                    x-text="index + 1"
                ></span>
                <span :class="step === index + 1 ? 'text-white' : 'text-slate-400'" x-text="label"></span>
                <span x-show="index < 2" class="text-slate-600 hidden sm:inline">→</span>
            </div>
        </template>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
            <p class="font-semibold text-rose-50 mb-2">Please fix the following before saving:</p>
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div
        id="wizard-step-error"
        x-show="stepError"
        x-cloak
        class="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100"
        x-text="stepError"
    ></div>

    <form
        method="POST"
        action="{{ route('admin.hr.employees.store') }}"
        novalidate
        @submit="onSubmit($event)"
        class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6"
    >
        @csrf
        <input type="hidden" name="_wizard_step" :value="step">

        {{-- Step 1: Bio data --}}
        <div x-show="step === 1" x-cloak class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Bio data</h2>
            <p class="text-sm text-slate-400">Personal and contact details. Fields marked <span class="text-rose-400">*</span> are required.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-slate-300">Title</label>
                    <select name="title" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                        <option value="">—</option>
                        @foreach ($employeeTitles as $employeeTitle)
                            <option value="{{ $employeeTitle }}" @selected(old('title') === $employeeTitle)>{{ $employeeTitle }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-300">First name <span class="text-rose-400">*</span></label>
                    <input name="first_name" :required="step === 1" value="{{ old('first_name') }}" data-wizard-required="1" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('first_name') border-rose-400 @else border-white/10 @enderror">
                    @error('first_name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-slate-300">Middle name</label>
                    <input name="middle_name" value="{{ old('middle_name') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div>
                    <label class="text-sm text-slate-300">Last name <span class="text-rose-400">*</span></label>
                    <input name="last_name" :required="step === 1" value="{{ old('last_name') }}" data-wizard-required="1" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('last_name') border-rose-400 @else border-white/10 @enderror">
                    @error('last_name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-slate-300">Gender</label>
                    <select name="gender" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                        <option value="">—</option>
                        @foreach (['male', 'female', 'other'] as $g)
                            <option value="{{ $g }}" @selected(old('gender') === $g)>{{ ucfirst($g) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-300">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div>
                    <label class="text-sm text-slate-300">National ID / NRC</label>
                    <input name="national_id" value="{{ old('national_id') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div>
                    <label class="text-sm text-slate-300">Nationality</label>
                    <input name="nationality" value="{{ old('nationality', 'Zambian') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div>
                    <label class="text-sm text-slate-300">Marital status</label>
                    <select name="marital_status" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                        <option value="">—</option>
                        @foreach (['single', 'married', 'divorced', 'widowed'] as $ms)
                            <option value="{{ $ms }}" @selected(old('marital_status') === $ms)>{{ ucfirst($ms) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-300">Work email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('email') border-rose-400 @else border-white/10 @enderror">
                    @error('email')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-slate-300">Personal email</label>
                    <input type="email" name="personal_email" value="{{ old('personal_email') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('personal_email') border-rose-400 @else border-white/10 @enderror">
                    @error('personal_email')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-slate-300">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div>
                    <label class="text-sm text-slate-300">Alternative phone</label>
                    <input name="alternative_phone" value="{{ old('alternative_phone') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-300">Residential address</label>
                    <textarea name="residential_address" rows="2" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">{{ old('residential_address') }}</textarea>
                </div>
            </div>
            <input type="hidden" name="employment_status" value="{{ old('employment_status', 'active') }}">
        </div>

        {{-- Step 2: Contract & pay --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Contract &amp; basic pay</h2>
            <p class="text-sm text-slate-400">Work placement, employee number, contract, and starting compensation. Fields marked <span class="text-rose-400">*</span> are required.</p>

            <div class="rounded-2xl border border-white/10 bg-black/20 p-4 space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Work placement</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-300">Department</label>
                        <select name="department_id" x-model="departmentId" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                            <option value="">—</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}" @selected((int) old('department_id') === $dept->id)>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-sm text-slate-300">Position</label>
                            @if ($canManagePositions ?? false)
                                <button type="button" @click="openPositionModal()" class="text-xs font-semibold text-cyan-300 hover:underline">+ New position</button>
                            @endif
                        </div>
                        <select name="position_id" x-model="positionId" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                            <option value="">—</option>
                            <template x-for="pos in filteredPositions" :key="pos.id">
                                <option :value="pos.id" x-text="pos.department_name ? pos.name + ' · ' + pos.department_name : pos.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-slate-500" x-show="departmentId">Showing positions for this department and organisation-wide roles.</p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Reports to</label>
                        <select name="reports_to_employee_id" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                            <option value="">—</option>
                            @foreach ($supervisors as $sup)
                                <option value="{{ $sup->id }}" @selected((int) old('reports_to_employee_id') === $sup->id)>{{ $sup->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Branch (work location)</label>
                        <select name="branch_id" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                            <option value="">—</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) old('branch_id') === $branch->id)>{{ $branch->name }}@if($branch->code) ({{ $branch->code }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-slate-300">Employee number</label>
                    <input name="employee_number" value="{{ old('employee_number') }}" placeholder="Leave blank to auto-generate" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white font-mono">
                </div>
                <div>
                    <label class="text-sm text-slate-300">Contract type <span class="text-rose-400">*</span></label>
                    <select name="contract_type_id" :required="step === 2" x-model="contractTypeId" @change="onContractTypeChange()" data-wizard-required="1" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('contract_type_id') border-rose-400 @else border-white/10 @enderror">
                        <option value="">Select contract type</option>
                        @foreach ($contractTypes as $type)
                            <option value="{{ $type->id }}" @selected((int) old('contract_type_id') === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('contract_type_id')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-slate-300">Contract start date <span class="text-rose-400">*</span></label>
                    <input type="date" name="contract_start_date" :required="step === 2" x-model="contractStartDate" @change="suggestEndDate()" data-wizard-required="1" value="{{ old('contract_start_date', now()->toDateString()) }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('contract_start_date') border-rose-400 @else border-white/10 @enderror">
                    @error('contract_start_date')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-slate-300">Date joined</label>
                    <input type="date" name="date_joined" value="{{ old('date_joined', now()->toDateString()) }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                </div>
                <div x-show="showEndDate" x-cloak>
                    <label class="text-sm text-slate-300">Contract end date <span class="text-rose-400">*</span></label>
                    <input type="date" name="contract_end_date" x-model="contractEndDate" :required="step === 2 && showEndDate" value="{{ old('contract_end_date') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('contract_end_date') border-rose-400 @else border-white/10 @enderror">
                    @error('contract_end_date')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    <p class="text-xs text-slate-500 mt-1" x-show="selectedType?.is_permanent === false && selectedType?.default_duration_months">Suggested from contract type default duration.</p>
                </div>
                @if ($canManageCompensation)
                    <div>
                        <label class="text-sm text-slate-300">Basic pay (ZMW) <span class="text-rose-400">*</span></label>
                        <input type="number" step="0.01" min="0" name="basic_pay" :required="step === 2 && canManageCompensation" data-wizard-required="1" value="{{ old('basic_pay') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('basic_pay') border-rose-400 @else border-white/10 @enderror">
                        @error('basic_pay')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Pay effective from</label>
                        <input type="date" name="compensation_effective_from" value="{{ old('compensation_effective_from', now()->toDateString()) }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                    </div>
                @else
                    <div class="md:col-span-2 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                        You do not have permission to set basic pay. Compensation can be added later on the employee profile.
                    </div>
                @endif
            </div>
        </div>

        {{-- Step 3: Payment --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <h2 class="text-lg font-semibold text-white">Payment details</h2>
            <p class="text-sm text-slate-400">Primary payroll destination. Required fields depend on bank, mobile money, or add later — marked <span class="text-rose-400">*</span> when applicable.</p>

            @if ($canManageBank)
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm cursor-pointer transition"
                           :class="payoutMethod === 'bank' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
                        <input type="radio" name="payout_method" value="bank" x-model="payoutMethod" class="sr-only"> Bank account
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm cursor-pointer transition"
                           :class="payoutMethod === 'mobile_money' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
                        <input type="radio" name="payout_method" value="mobile_money" x-model="payoutMethod" class="sr-only"> Mobile money
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm cursor-pointer transition"
                           :class="payoutMethod === 'none' ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100' : 'border-white/10 text-slate-300'">
                        <input type="radio" name="payout_method" value="none" x-model="payoutMethod" class="sr-only"> Add later
                    </label>
                </div>

                <div x-show="payoutMethod === 'bank'" class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-300">Bank / institution</label>
                        <select name="financial_institution_id" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                            <option value="">Other / type name below</option>
                            @foreach ($financialInstitutions as $inst)
                                <option value="{{ $inst->id }}" @selected((int) old('financial_institution_id') === $inst->id)>{{ $inst->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Bank name (if not in list)</label>
                        <input name="bank_name" value="{{ old('bank_name') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Branch name</label>
                        <input name="branch_name" value="{{ old('branch_name') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Branch code</label>
                        <input name="branch_code" value="{{ old('branch_code') }}" class="mt-1 w-full rounded-2xl bg-white/10 border border-white/10 px-4 py-2 text-white">
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Account name <span class="text-rose-400">*</span></label>
                        <input name="account_name" :required="payoutMethod === 'bank' && step === 3" value="{{ old('account_name') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('account_name') border-rose-400 @else border-white/10 @enderror">
                        @error('account_name')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Account number <span class="text-rose-400">*</span></label>
                        <input name="account_number" :required="payoutMethod === 'bank' && step === 3" value="{{ old('account_number') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('account_number') border-rose-400 @else border-white/10 @enderror">
                        @error('account_number')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
                    </div>
                    <input type="hidden" name="account_type" value="bank">
                </div>

                <div x-show="payoutMethod === 'mobile_money'" class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-300">Mobile money provider <span class="text-rose-400">*</span></label>
                        <select name="mobile_money_provider" :required="payoutMethod === 'mobile_money' && step === 3" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('mobile_money_provider') border-rose-400 @else border-white/10 @enderror">
                            <option value="">Select provider</option>
                            @foreach ($mobileMoneyProviders as $provider)
                                <option value="{{ $provider }}" @selected(old('mobile_money_provider') === $provider)>{{ $provider }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-300">Registered name <span class="text-rose-400">*</span></label>
                        <input name="mobile_account_name" :required="payoutMethod === 'mobile_money' && step === 3" value="{{ old('mobile_account_name') }}" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('mobile_account_name') border-rose-400 @else border-white/10 @enderror">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm text-slate-300">Mobile number <span class="text-rose-400">*</span></label>
                        <input name="mobile_number" :required="payoutMethod === 'mobile_money' && step === 3" value="{{ old('mobile_number') }}" placeholder="e.g. 0971234567" class="mt-1 w-full rounded-2xl bg-white/10 border px-4 py-2 text-white @error('mobile_number') border-rose-400 @else border-white/10 @enderror">
                    </div>
                </div>
            @else
                <input type="hidden" name="payout_method" value="none">
                <p class="text-sm text-slate-400">You do not have permission to capture payment details. They can be added later on the employee profile.</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-white/10">
            <button type="button" x-show="step > 1" @click="step--" class="rounded-2xl border border-white/10 px-5 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10">
                Back
            </button>
            <div class="flex gap-3 ml-auto">
                <a href="{{ route('admin.hr.employees.index') }}" class="rounded-2xl border border-white/10 px-5 py-2.5 text-sm text-slate-300 hover:bg-white/10">Cancel</a>
                <button type="button" x-show="step < 3" @click="nextStep()" class="btn-primary rounded-2xl px-5 py-2.5 text-sm font-semibold shadow-lg">
                    Continue
                </button>
                <button type="submit" x-show="step === 3" class="btn-primary rounded-2xl px-6 py-2.5 text-sm font-semibold shadow-lg">
                    Create employee
                </button>
            </div>
        </div>
    </form>

    <div
        x-show="showPositionModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @keydown.escape.window="showPositionModal = false"
    >
        <div @click.outside="showPositionModal = false" class="w-full max-w-md rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-white">New position</h3>
                    <p class="text-sm text-slate-400">Optionally link the role to a department.</p>
                </div>
                <button type="button" @click="showPositionModal = false" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div>
                <label class="text-sm text-slate-300">Position name <span class="text-rose-400">*</span></label>
                <input x-model="positionForm.name" type="text" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white" placeholder="e.g. HR Officer">
            </div>
            <div>
                <label class="text-sm text-slate-300">Code</label>
                <input x-model="positionForm.code" type="text" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white" placeholder="Auto-generated if empty">
            </div>
            <div>
                <label class="text-sm text-slate-300">Department</label>
                <select x-model="positionForm.department_id" class="mt-1 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2 text-white">
                    <option value="">Organisation-wide (no department)</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <p x-show="positionError" x-text="positionError" class="text-sm text-rose-300"></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showPositionModal = false" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300">Cancel</button>
                <button type="button" @click="submitNewPosition()" :disabled="positionSaving" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-50">
                    <span x-show="!positionSaving">Save position</span>
                    <span x-show="positionSaving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function employeeCreateWizard(config) {
        return {
            step: {{ $initialWizardStep }},
            stepError: '',
            canManageCompensation: @js($canManageCompensation),
            canManageBank: @js($canManageBank),
            positions: config.positions || [],
            departmentId: @js(old('department_id', '')),
            positionId: @js(old('position_id', '')),
            showPositionModal: false,
            positionSaving: false,
            positionError: '',
            positionForm: { name: '', code: '', department_id: @js(old('department_id', '')) },
            canManagePositions: config.canManagePositions,
            positionStoreUrl: config.positionStoreUrl,
            csrfToken: config.csrfToken,
            get filteredPositions() {
                const list = this.positions.filter(p => p.is_active !== false);
                if (!this.departmentId) {
                    return list;
                }
                return list.filter(p => !p.department_id || String(p.department_id) === String(this.departmentId));
            },
            openPositionModal() {
                this.positionError = '';
                this.positionForm.department_id = this.departmentId || '';
                this.showPositionModal = true;
            },
            async submitNewPosition() {
                if (!this.positionForm.name.trim()) {
                    this.positionError = 'Position name is required.';
                    return;
                }
                this.positionSaving = true;
                this.positionError = '';
                try {
                    const res = await fetch(this.positionStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            name: this.positionForm.name,
                            code: this.positionForm.code || null,
                            department_id: this.positionForm.department_id || null,
                            is_active: true,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        this.positionError = firstError || data.message || 'Could not create position.';
                        return;
                    }
                    const pos = data.position;
                    this.positions.push({
                        id: pos.id,
                        name: pos.name,
                        department_id: pos.department_id,
                        department_name: pos.department_name || '',
                    });
                    this.positionId = String(pos.id);
                    this.showPositionModal = false;
                    this.positionForm = { name: '', code: '', department_id: this.departmentId || '' };
                } catch (e) {
                    this.positionError = 'Could not create position.';
                } finally {
                    this.positionSaving = false;
                }
            },
            contractTypes: config.contractTypes || [],
            contractTypeId: @js(old('contract_type_id', '')),
            contractStartDate: config.defaultStartDate,
            contractEndDate: @js(old('contract_end_date', '')),
            payoutMethod: @js(old('payout_method', 'bank')),
            get selectedType() {
                return this.contractTypes.find(t => String(t.id) === String(this.contractTypeId));
            },
            get showEndDate() {
                const t = this.selectedType;
                if (!t) return false;
                if (t.is_permanent) return false;
                return t.has_end_date !== false;
            },
            fieldLabel(name) {
                const form = this.$el.querySelector('form');
                const el = form?.querySelector(`[name="${name}"]`);
                const label = el?.closest('div')?.querySelector('label');
                return label?.innerText?.replace(/\*/g, '').trim() || name;
            },
            validateCurrentStep() {
                this.stepError = '';
                const form = this.$el.querySelector('form');
                if (!form) return true;

                if (this.step === 1) {
                    const missing = [];
                    for (const name of ['first_name', 'last_name']) {
                        const el = form.querySelector(`[name="${name}"]`);
                        if (!el || !String(el.value).trim()) {
                            missing.push(this.fieldLabel(name));
                        }
                    }
                    for (const name of ['email', 'personal_email']) {
                        const el = form.querySelector(`[name="${name}"]`);
                        if (el && el.value && !el.checkValidity()) {
                            missing.push(`${this.fieldLabel(name)} (valid email required)`);
                        }
                    }
                    if (missing.length) {
                        this.stepError = 'Please complete required fields: ' + missing.join(', ') + '.';
                        return false;
                    }
                    return true;
                }

                if (this.step === 2) {
                    const missing = [];
                    const contractType = form.querySelector('[name=contract_type_id]');
                    const start = form.querySelector('[name=contract_start_date]');
                    if (!contractType?.value) missing.push('Contract type');
                    if (!start?.value) missing.push('Contract start date');
                    if (this.canManageCompensation) {
                        const pay = form.querySelector('[name=basic_pay]');
                        if (pay && (pay.value === '' || Number(pay.value) < 0)) missing.push('Basic pay (ZMW)');
                    }
                    if (this.showEndDate) {
                        const end = form.querySelector('[name=contract_end_date]');
                        if (!end?.value) missing.push('Contract end date');
                    }
                    if (missing.length) {
                        this.stepError = 'Please complete required fields: ' + missing.join(', ') + '.';
                        return false;
                    }
                    return true;
                }

                if (this.step === 3 && this.canManageBank) {
                    const missing = [];
                    if (this.payoutMethod === 'bank') {
                        for (const name of ['account_name', 'account_number']) {
                            const el = form.querySelector(`[name="${name}"]`);
                            if (!el || !String(el.value).trim()) missing.push(this.fieldLabel(name));
                        }
                    } else if (this.payoutMethod === 'mobile_money') {
                        for (const name of ['mobile_money_provider', 'mobile_account_name', 'mobile_number']) {
                            const el = form.querySelector(`[name="${name}"]`);
                            if (!el || !String(el.value).trim()) missing.push(this.fieldLabel(name));
                        }
                    }
                    if (missing.length) {
                        this.stepError = 'Please complete payment fields: ' + missing.join(', ') + '.';
                        return false;
                    }
                }

                return true;
            },
            onSubmit(event) {
                if (!this.validateCurrentStep()) {
                    event.preventDefault();
                    document.getElementById('wizard-step-error')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
            },
            onContractTypeChange() {
                this.suggestEndDate();
            },
            suggestEndDate() {
                const t = this.selectedType;
                if (!t || t.is_permanent || !t.default_duration_months || !this.contractStartDate) return;
                const start = new Date(this.contractStartDate + 'T12:00:00');
                start.setMonth(start.getMonth() + parseInt(t.default_duration_months, 10));
                this.contractEndDate = start.toISOString().slice(0, 10);
                const input = document.querySelector('input[name=contract_end_date]');
                if (input) input.value = this.contractEndDate;
            },
            nextStep() {
                if (!this.validateCurrentStep()) {
                    document.getElementById('wizard-step-error')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
                this.stepError = '';
                this.step++;
                this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            },
            init() {
                if (this.contractTypeId) {
                    this.onContractTypeChange();
                }
                this.$watch('departmentId', (value) => {
                    if (this.positionId) {
                        const stillValid = this.filteredPositions.some(p => String(p.id) === String(this.positionId));
                        if (!stillValid) {
                            this.positionId = '';
                        }
                    }
                });
            },
        };
    }
</script>
@endpush
