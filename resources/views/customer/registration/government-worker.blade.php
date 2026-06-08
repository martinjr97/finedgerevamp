@extends('layouts.auth')

@section('title', 'Government Worker Registration | ' . config('app.system_name'))
@section('heading', 'Government Worker Registration')
@section('subheading', 'Complete your registration request as a government worker.')

@section('content')
    @php
        $isEditing = isset($editingReference);
        $formAction = $isEditing
            ? route('customer.register-request.government-worker.update', $editingReference)
            : route('customer.register-request.government-worker.store');
        $ministryOtherValue = $ministryOtherValue ?? \App\Support\PublicRegistrationPaths::MINISTRY_OTHER;
        $selectedMinistry = old('ministry_id');
        $showEmployerField = $selectedMinistry === $ministryOtherValue
            || (empty($selectedMinistry) && filled(old('employer_name')) && empty(old('ministry_id')));
        if ($selectedMinistry && $selectedMinistry !== $ministryOtherValue && $selectedMinistry !== '') {
            $showEmployerField = false;
        }
        $financialInstitutions = $financialInstitutions ?? collect();
        $selectedFinancialInstitutionId = (string) old('bank_financial_institution_id');
        $selectedFinancialInstitutionBranchId = (string) old('bank_financial_institution_branch_id');
        $inputClass = 'mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25';
    @endphp

    @if(!$isEditing)
        @include('customer.registration.partials.retrieve-modal', ['triggerClass' => 'mb-6'])
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold mb-1">Please fix the errors below and try again.</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('customer.registration.partials.common-fields')

        @include('customer.registration.partials.customer-address', [
            'provinces' => $provinces,
            'districts' => $districts,
            'heading' => 'Your address',
            'description' => 'Your residential address where we can reach you.',
            'pairId' => 'home',
            'inputClass' => $inputClass,
        ])

        <div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
            <h2 class="text-lg font-semibold text-slate-900">Employment information</h2>

            @if($ministries->isNotEmpty())
                <div>
                    <label for="ministry_id" class="block text-sm font-medium text-slate-800">
                        Ministry <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="ministry_id"
                        name="ministry_id"
                        required
                        class="{{ $inputClass }}"
                    >
                        <option value="">Select ministry</option>
                        @foreach($ministries as $ministry)
                            <option value="{{ $ministry->id }}" @selected((string) $selectedMinistry === (string) $ministry->id)>
                                {{ $ministry->name }}
                            </option>
                        @endforeach
                        <option value="{{ $ministryOtherValue }}" @selected((string) $selectedMinistry === $ministryOtherValue)>
                            Other (not listed)
                        </option>
                    </select>
                    @error('ministry_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>

                <div id="employer_name_wrap" class="{{ $showEmployerField ? '' : 'hidden' }}">
                    <label for="employer_name" class="block text-sm font-medium text-slate-800">
                        Employer / ministry name <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="employer_name"
                        name="employer_name"
                        type="text"
                        value="{{ old('employer_name') }}"
                        class="{{ $inputClass }}"
                        placeholder="Enter your ministry or employer name"
                        @unless($showEmployerField) disabled @endunless
                    >
                    @error('employer_name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <label for="employer_name" class="block text-sm font-medium text-slate-800">
                        Employer / ministry name <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="employer_name"
                        name="employer_name"
                        type="text"
                        value="{{ old('employer_name') }}"
                        required
                        class="{{ $inputClass }}"
                    >
                    @error('employer_name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
            @endif

            <div>
                <label for="department" class="block text-sm font-medium text-slate-800">Department (optional)</label>
                <input id="department" name="department" type="text" value="{{ old('department') }}" class="{{ $inputClass }}">
                @error('department')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="employee_number" class="block text-sm font-medium text-slate-800">Employee / payroll number <span class="text-red-500">*</span></label>
                    <input id="employee_number" name="employee_number" type="text" value="{{ old('employee_number') }}" required class="{{ $inputClass }}">
                    @error('employee_number')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="date_of_employment" class="block text-sm font-medium text-slate-800">Date of employment <span class="text-red-500">*</span></label>
                    <input
                        id="date_of_employment"
                        name="date_of_employment"
                        type="date"
                        value="{{ old('date_of_employment') }}"
                        max="{{ date('Y-m-d') }}"
                        required
                        class="{{ $inputClass }}"
                    >
                    @error('date_of_employment')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="gross_salary" class="block text-sm font-medium text-slate-800">Gross monthly salary (optional)</label>
                    <input id="gross_salary" name="gross_salary" type="number" step="0.01" min="0" value="{{ old('gross_salary') }}" class="{{ $inputClass }}">
                    @error('gross_salary')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="net_salary" class="block text-sm font-medium text-slate-800">Net monthly salary <span class="text-red-500">*</span></label>
                    <input id="net_salary" name="net_salary" type="number" step="0.01" min="0" value="{{ old('net_salary') }}" required class="{{ $inputClass }}">
                    @error('net_salary')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h3 class="text-base font-semibold text-slate-900">Salary bank details</h3>
                <p class="text-sm text-slate-600">Provide the bank account where you receive your salary.</p>

                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="bank_financial_institution_id" class="block text-sm font-medium text-slate-800">Bank <span class="text-red-500">*</span></label>
                        <select id="bank_financial_institution_id" name="bank_financial_institution_id" required class="{{ $inputClass }}" @disabled($financialInstitutions->isEmpty())>
                            <option value="">Select bank</option>
                            @foreach($financialInstitutions as $institution)
                                <option value="{{ $institution->id }}" @selected($selectedFinancialInstitutionId === (string) $institution->id)>
                                    {{ $institution->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('bank_financial_institution_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bank_financial_institution_branch_id" class="block text-sm font-medium text-slate-800">Branch <span class="text-red-500">*</span></label>
                        <select id="bank_financial_institution_branch_id" name="bank_financial_institution_branch_id" required class="{{ $inputClass }}" @disabled($financialInstitutions->isEmpty())>
                            <option value="">Select branch</option>
                            @foreach($financialInstitutions as $institution)
                                @foreach($institution->branches as $branch)
                                    <option value="{{ $branch->id }}"
                                            data-financial-institution-id="{{ $institution->id }}"
                                            @selected($selectedFinancialInstitutionBranchId === (string) $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                        @error('bank_financial_institution_branch_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bank_account_name" class="block text-sm font-medium text-slate-800">Account name <span class="text-red-500">*</span></label>
                        <input id="bank_account_name" name="bank_account_name" type="text" value="{{ old('bank_account_name') }}" required class="{{ $inputClass }}" placeholder="As it appears on the bank account">
                        @error('bank_account_name')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bank_account_number" class="block text-sm font-medium text-slate-800">Account number <span class="text-red-500">*</span></label>
                        <input id="bank_account_number" name="bank_account_number" type="text" value="{{ old('bank_account_number') }}" required class="{{ $inputClass }}" maxlength="50">
                        @error('bank_account_number')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
            <h2 class="text-lg font-semibold text-slate-900">Workplace address</h2>
            <p class="text-sm text-slate-600">Where you are currently employed (office or station).</p>

            <div>
                <label for="work_address_line1" class="block text-sm font-medium text-slate-800">Address line 1 <span class="text-red-500">*</span></label>
                <input id="work_address_line1" name="work_address_line1" type="text" value="{{ old('work_address_line1') }}" required class="{{ $inputClass }}">
                @error('work_address_line1')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="work_address_line2" class="block text-sm font-medium text-slate-800">Address line 2 (optional)</label>
                <input id="work_address_line2" name="work_address_line2" type="text" value="{{ old('work_address_line2') }}" class="{{ $inputClass }}">
                @error('work_address_line2')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="work_city" class="block text-sm font-medium text-slate-800">City / town (optional)</label>
                    <input id="work_city" name="work_city" type="text" value="{{ old('work_city') }}" class="{{ $inputClass }}">
                    @error('work_city')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="work_province_id" class="block text-sm font-medium text-slate-800">Province <span class="text-red-500">*</span></label>
                    <select
                        id="work_province_id"
                        name="work_province_id"
                        required
                        data-province-select
                        data-province-district-pair="work"
                        class="{{ $inputClass }}"
                    >
                        <option value="">Select province</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province->id }}" @selected((int) old('work_province_id') === $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                    @error('work_province_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="work_district_id" class="block text-sm font-medium text-slate-800">District <span class="text-red-500">*</span></label>
                    <select
                        id="work_district_id"
                        name="work_district_id"
                        required
                        data-district-select
                        data-province-district-pair="work"
                        data-placeholder="Select district"
                        class="{{ $inputClass }}"
                    >
                        <option value="">Select district</option>
                        @foreach($districts as $district)
                            <option
                                value="{{ $district->id }}"
                                data-province-id="{{ $district->province_id }}"
                                @selected((int) old('work_district_id') === $district->id)
                            >{{ $district->name }}</option>
                        @endforeach
                    </select>
                    @error('work_district_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        @include('customer.registration.partials.kyc-uploads')

        <p class="text-xs text-slate-500">
            By submitting, you are sending a request only. Your account will be created after review and approval.
        </p>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('customer.register-request.create') }}" class="text-sm text-slate-600 hover:text-slate-800">Back</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg">
                {{ $isEditing ? 'Update request' : 'Submit registration request' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('partials.province-district-cascade')
    <script>
        (() => {
            const ministrySelect = document.getElementById('ministry_id');
            const employerWrap = document.getElementById('employer_name_wrap');
            const employerInput = document.getElementById('employer_name');
            const otherValue = @json($ministryOtherValue);

            if (!ministrySelect || !employerWrap || !employerInput) {
                return;
            }

            const syncEmployerField = () => {
                const isOther = ministrySelect.value === otherValue;
                employerWrap.classList.toggle('hidden', !isOther);
                employerInput.disabled = !isOther;
                if (!isOther) {
                    employerInput.value = '';
                }
            };

            ministrySelect.addEventListener('change', syncEmployerField);
            syncEmployerField();
        })();

        (() => {
            const bankSelect = document.getElementById('bank_financial_institution_id');
            const branchSelect = document.getElementById('bank_financial_institution_branch_id');

            if (!bankSelect || !branchSelect) {
                return;
            }

            const syncBranches = () => {
                const institutionId = bankSelect.value;
                let hasVisible = false;

                branchSelect.querySelectorAll('option[data-financial-institution-id]').forEach((option) => {
                    const matches = option.dataset.financialInstitutionId === institutionId;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (matches) {
                        hasVisible = true;
                    }
                });

                if (!hasVisible) {
                    branchSelect.value = '';
                } else if (branchSelect.selectedOptions[0]?.disabled) {
                    branchSelect.value = '';
                }
            };

            bankSelect.addEventListener('change', syncBranches);
            syncBranches();
        })();
    </script>
@endpush
