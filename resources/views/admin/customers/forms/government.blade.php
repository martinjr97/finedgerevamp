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
@endphp

<form action="{{ route('admin.customers.store') }}" method="POST" class="space-y-8">
    @csrf
    <input type="hidden" name="loan_product_id" value="{{ $product->id }}">
    @if(!empty($registrationRequestId))
        <input type="hidden" name="registration_request_id" value="{{ $registrationRequestId }}">
    @endif

    {{-- Bio Data Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>
            Bio Data
        </h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">First Name <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('first_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Last Name <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('last_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Email <span class="{{ $requiredClass }}">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('email')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            @include('partials.zambian-phone-field', [
                'name' => 'phone',
                'label' => 'Mobile number',
                'inputClass' => "mt-2 w-full rounded-2xl {$inputClass} text-white px-4 py-3 {$inputFocusClass}",
                'labelClass' => $labelClass,
                'errorClass' => $errorClass,
                'helpClass' => $helpClass,
            ])
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Referred By</label>
                <select name="referred_by" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                    <option value="">No referral</option>
                    @foreach ($referredByCustomers as $referrer)
                        <option value="{{ $referrer->id }}" @selected(old('referred_by') == $referrer->id)>
                            {{ $referrer->full_name }}{{ $referrer->phone ? ' - '.$referrer->phone : '' }}
                        </option>
                    @endforeach
                </select>
                @error('referred_by')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->subYears(16)->format('Y-m-d') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('date_of_birth')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs {{ $helpClass }}">Customer must be at least 16 years old</p>
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Gender</label>
                <select name="gender" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                    <option value="">Select Gender</option>
                    <option value="male" @selected(old('gender') === 'male')>Male</option>
                    <option value="female" @selected(old('gender') === 'female')>Female</option>
                    <option value="other" @selected(old('gender') === 'other')>Other</option>
                </select>
                @error('gender')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                @include('partials.customer-identity-fields', [
                    'nationalIdType' => old('national_id_type', isset($customer) ? $customer->national_id_type : null),
                    'nationalIdValue' => old('national_id', isset($customer) ? ($customer->national_id ?? '') : ''),
                    'tpinValue' => old('tpin', isset($customer) ? ($customer->tpin ?? '') : ''),
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
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Work Information</h2>
        <div class="grid gap-6 md:grid-cols-2">
            @include('partials.customer-employee-number-field', [
                'employeeNumberValue' => old('employee_number'),
                'labelClass' => $labelClass,
                'inputClass' => 'mt-2 w-full rounded-2xl ' . $inputClass . ' ' . $inputFocusClass . ' text-white px-4 py-3',
                'errorClass' => $errorClass,
                'requiredClass' => $requiredClass,
            ])
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Ministry <span class="{{ $requiredClass }}">*</span></label>
                <select name="ministry_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                    <option value="">Select Ministry</option>
                    @foreach ($ministries as $ministry)
                        <option value="{{ $ministry->id }}" @selected(old('ministry_id') == $ministry->id)>
                            {{ $ministry->name }}
                        </option>
                    @endforeach
                </select>
                @error('ministry_id')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Date of Employment <span class="{{ $requiredClass }}">*</span></label>
                <input type="date" name="date_of_employment" value="{{ old('date_of_employment') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('date_of_employment')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Contract End Date</label>
                <input type="date" name="contract_end_date" value="{{ old('contract_end_date') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('contract_end_date')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Gross Salary <span class="{{ $requiredClass }}">*</span></label>
                <input type="number" name="gross_salary" value="{{ old('gross_salary') }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3" placeholder="0.00">
                @error('gross_salary')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Net Salary <span class="{{ $requiredClass }}">*</span></label>
                <input type="number" name="net_salary" value="{{ old('net_salary') }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3" placeholder="0.00">
                @error('net_salary')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Deductions</label>
                <input type="number" name="deductions" value="{{ old('deductions') }}" step="0.01" min="0" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3" placeholder="0.00">
                @error('deductions')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Verified By (Relationship Manager) <span class="{{ $requiredClass }}">*</span></label>
                <select name="verified_by" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                    <option value="">Select Relationship Manager</option>
                    @foreach ($relationshipManagers as $manager)
                        <option value="{{ $manager->id }}" @selected(old('verified_by') == $manager->id)>
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

    {{-- Work Address Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Work Address</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Work Address Line 1</label>
                <input type="text" name="work_address_line1" value="{{ old('work_address_line1') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('work_address_line1')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Work Address Line 2</label>
                <input type="text" name="work_address_line2" value="{{ old('work_address_line2') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('work_address_line2')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Work City</label>
                <input type="text" name="work_city" value="{{ old('work_city') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('work_city')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Work Province</label>
                <select
                    name="work_province_id"
                    id="work_province_id"
                    data-province-select
                    data-province-district-pair="work"
                    data-no-select-search="true"
                    class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3"
                >
                    <option value="">Select Province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected(old('work_province_id') == $province->id)>
                            {{ $province->name }}
                        </option>
                    @endforeach
                </select>
                @error('work_province_id')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Work District</label>
                <select
                    name="work_district_id"
                    id="work_district_id"
                    data-district-select
                    data-province-district-pair="work"
                    data-placeholder="Select District"
                    data-no-select-search="true"
                    class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3"
                >
                    <option value="">Select District</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" data-province-id="{{ $district->province_id }}" @selected(old('work_district_id') == $district->id)>
                            {{ $district->name }}
                        </option>
                    @endforeach
                </select>
                @error('work_district_id')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Work Postal Code</label>
                <input type="text" name="work_postal_code" value="{{ old('work_postal_code') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('work_postal_code')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Work Country</label>
                <input type="text" name="work_country" value="{{ old('work_country', 'Zambia') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('work_country')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Address Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Customer Address</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 1 <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="address_line1" value="{{ old('address_line1') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('address_line1')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 2</label>
                <input type="text" name="address_line2" value="{{ old('address_line2') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('address_line2')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">City <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="city" value="{{ old('city') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('city')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Province</label>
                <select name="province_id" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                    <option value="">Select Province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>
                            {{ $province->name }}
                        </option>
                    @endforeach
                </select>
                @error('province_id')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Postal Code</label>
                <input type="text" name="postal_code" value="{{ old('postal_code') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('postal_code')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Country <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="country" value="{{ old('country', 'Zambia') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
                @error('country')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    @include('partials.admin.customer-next-of-kin-fields')

    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('admin.customers.select-product-type') }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
            Create Customer
        </button>
    </div>
</form>

@include('partials.province-district-cascade')
