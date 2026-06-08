@php
    // Get form colors from config
    $preset = config('forms.active_preset', 'blue');
    $presetColors = config("forms.presets.{$preset}", config('forms.presets.blue'));
    $colors = array_merge(config('forms.colors', []), $presetColors);
    
    // Build color classes
    $sectionClass = "border-2 border-{$colors['section_border']} bg-{$colors['section_background']}";
    $inputClass = "bg-{$colors['input_background']} border border-{$colors['input_border']}";
    $inputFocusClass = "focus:border-2 focus:border-{$colors['input_focus_border']} focus:ring-{$colors['input_focus_ring']}";
    $labelClass = "text-{$colors['label']}";
    $headingClass = "text-{$colors['heading']}";
    $errorClass = "text-{$colors['error']}";
    $helpClass = "text-{$colors['help']}";
    $requiredClass = "text-{$colors['required']}";
    $buttonPrimaryClass = "bg-gradient-to-r from-{$colors['button_primary']} to-{$colors['button_secondary']} shadow-lg shadow-{$colors['button_shadow']}";
    $buttonSecondaryClass = "border border-white/10 bg-white/5 hover:bg-white/10 text-white";
    $headingAccentClass = "text-{$colors['input_focus_border']}";
@endphp

<form action="{{ route('admin.customers.update', $customer) }}" method="POST" class="space-y-8">
    @csrf
    @method('PUT')
    
    {{-- Hidden loan_product_id field --}}
    <input type="hidden" name="loan_product_id" value="{{ old('loan_product_id', $customer->loan_product_id) }}">

    {{-- Company Selection Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>
            Company Information
        </h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Company <span class="{{ $requiredClass }}">*</span></label>
                <select name="company_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                    <option value="">Select Company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id', $customer->company_id) == $company->id)>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
                @error('company_id')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Bio Data Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Bio Data</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">First Name <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('first_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Last Name <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('last_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Email <span class="{{ $requiredClass }}">*</span></label>
                <input type="email" name="email" value="{{ old('email', $customer->email) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('email')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
                @error('phone')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}" max="{{ now()->subYears(16)->format('Y-m-d') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('date_of_birth')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs {{ $helpClass }}">Customer must be at least 16 years old</p>
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Gender</label>
                <select name="gender" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                    <option value="">Select Gender</option>
                    <option value="male" @selected(old('gender', $customer->gender) === 'male')>Male</option>
                    <option value="female" @selected(old('gender', $customer->gender) === 'female')>Female</option>
                    <option value="other" @selected(old('gender', $customer->gender) === 'other')>Other</option>
                </select>
                @error('gender')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                @include('partials.customer-identity-fields', [
                    'nationalIdType' => old('national_id_type', $customer->national_id_type ?? null),
                    'nationalIdValue' => old('national_id', $customer->national_id ?? ''),
                    'tpinValue' => old('tpin', $customer->tpin ?? ''),
                    'labelClass' => $labelClass,
                    'inputClass' => 'mt-2 w-full rounded-2xl ' . $inputClass . ' ' . $inputFocusClass . ' text-white px-4 py-3',
                    'errorClass' => $errorClass,
                    'helpClass' => 'mt-1 text-xs text-slate-400',
                    'requiredClass' => $requiredClass,
                ])
            </div>
        </div>
    </div>

    {{-- Work Information Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>
            Work Information
        </h2>
        <div class="grid gap-6 md:grid-cols-2">
            @include('partials.customer-employee-number-field', [
                'employeeNumberValue' => old('employee_number', $customer->employee_number ?? ''),
                'labelClass' => $labelClass,
                'inputClass' => 'mt-2 w-full rounded-2xl ' . $inputClass . ' ' . $inputFocusClass . ' text-white px-4 py-3',
                'errorClass' => $errorClass,
                'requiredClass' => $requiredClass,
            ])
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Position <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="position" value="{{ old('position', $customer->position) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., Manager, Analyst">
                @error('position')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Unit</label>
                <input type="text" name="unit" value="{{ old('unit', $customer->unit) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., Sales Unit">
                @error('unit')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Department <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="department" value="{{ old('department', $customer->department) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., Finance, Operations">
                @error('department')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Start Date of Contract <span class="{{ $requiredClass }}">*</span></label>
                <input type="date" name="date_of_employment" value="{{ old('date_of_employment', $customer->date_of_employment?->format('Y-m-d')) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('date_of_employment')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">End Date of Contract</label>
                <input type="date" name="contract_end_date" value="{{ old('contract_end_date', $customer->contract_end_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('contract_end_date')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Gross Salary <span class="{{ $requiredClass }}">*</span></label>
                <input type="number" name="gross_salary" value="{{ old('gross_salary', $customer->gross_salary) }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="0.00">
                @error('gross_salary')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Net Salary <span class="{{ $requiredClass }}">*</span></label>
                <input type="number" name="net_salary" value="{{ old('net_salary', $customer->net_salary) }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="0.00">
                @error('net_salary')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Verified By (Relationship Manager) <span class="{{ $requiredClass }}">*</span></label>
                <select name="verified_by" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                    <option value="">Select Relationship Manager</option>
                    @foreach ($relationshipManagers as $manager)
                        <option value="{{ $manager->id }}" @selected(old('verified_by', $customer->verified_by) == $manager->id)>
                            {{ $manager->full_name }} ({{ $manager->email }})
                        </option>
                    @endforeach
                </select>
                @error('verified_by')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Address Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>
            Address Information
        </h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 1 <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="address_line1" value="{{ old('address_line1', $customer->address_line1) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('address_line1')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 2</label>
                <input type="text" name="address_line2" value="{{ old('address_line2', $customer->address_line2) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('address_line2')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">City <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="city" value="{{ old('city', $customer->city) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('city')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">State/Province</label>
                <input type="text" name="state" value="{{ old('state', $customer->state) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('state')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Postal Code</label>
                <input type="text" name="postal_code" value="{{ old('postal_code', $customer->postal_code) }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('postal_code')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Country <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="country" value="{{ old('country', $customer->country ?? 'Zambia') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('country')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
            Update Customer
        </button>
    </div>
</form>

