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

    {{-- Customer Group Selection Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Customer Group</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Group <span class="{{ $requiredClass }}">*</span></label>
                <select name="customer_group_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                    <option value="">Select Customer Group</option>
                    @foreach ($customerGroups as $group)
                        <option value="{{ $group->id }}" @selected(old('customer_group_id') == $group->id)>
                            {{ $group->name }} ({{ ucfirst($group->risk_level) }} Risk)
                        </option>
                    @endforeach
                </select>
                @error('customer_group_id')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs {{ $helpClass }}">Select the appropriate group based on customer's collateral value and risk assessment.</p>
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
                <input type="text" name="first_name" value="{{ old('first_name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('first_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Last Name <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('last_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Email <span class="{{ $requiredClass }}">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('email')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Phone</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }} zambian-phone-input" placeholder="260978232334">
                @error('phone')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Referred By</label>
                <select name="referred_by" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
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
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->subYears(16)->format('Y-m-d') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('date_of_birth')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs {{ $helpClass }}">Customer must be at least 16 years old</p>
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Gender</label>
                <select name="gender" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
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

    {{-- Address Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Customer Address</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 1 <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="address_line1" value="{{ old('address_line1') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('address_line1')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 2</label>
                <input type="text" name="address_line2" value="{{ old('address_line2') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('address_line2')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">City <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="city" value="{{ old('city') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('city')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Province</label>
                <select name="province_id" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
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
                <input type="text" name="postal_code" value="{{ old('postal_code') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('postal_code')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Country <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="country" value="{{ old('country', 'Zambia') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('country')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Next of Kin Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Next of Kin Information</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Name <span class="{{ $requiredClass }}">*</span></label>
                <input type="text" name="next_of_kin_name" value="{{ old('next_of_kin_name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('next_of_kin_name')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Phone <span class="{{ $requiredClass }}">*</span></label>
                                <input type="text" name="next_of_kin_phone" value="{{ old('next_of_kin_phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }} zambian-phone-input" placeholder="260978232334" required>
                @error('next_of_kin_phone')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Relationship <span class="{{ $requiredClass }}">*</span></label>
                <select name="next_of_kin_relationship" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                    <option value="">Select Relationship</option>
                    <option value="spouse" @selected(old('next_of_kin_relationship') == 'spouse')>Spouse</option>
                    <option value="parent" @selected(old('next_of_kin_relationship') == 'parent')>Parent</option>
                    <option value="sibling" @selected(old('next_of_kin_relationship') == 'sibling')>Sibling</option>
                    <option value="child" @selected(old('next_of_kin_relationship') == 'child')>Child</option>
                    <option value="relative" @selected(old('next_of_kin_relationship') == 'relative')>Relative</option>
                    <option value="friend" @selected(old('next_of_kin_relationship') == 'friend')>Friend</option>
                    <option value="other" @selected(old('next_of_kin_relationship') == 'other')>Other</option>
                </select>
                @error('next_of_kin_relationship')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 1</label>
                <input type="text" name="next_of_kin_address_line1" value="{{ old('next_of_kin_address_line1') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('next_of_kin_address_line1')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium {{ $labelClass }}">Address Line 2</label>
                <input type="text" name="next_of_kin_address_line2" value="{{ old('next_of_kin_address_line2') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('next_of_kin_address_line2')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">City</label>
                <input type="text" name="next_of_kin_city" value="{{ old('next_of_kin_city') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('next_of_kin_city')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Country</label>
                <input type="text" name="next_of_kin_country" value="{{ old('next_of_kin_country', 'Zambia') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                @error('next_of_kin_country')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Work Information Section --}}
    <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
            <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Work Information</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Employment Status <span class="{{ $requiredClass }}">*</span></label>
                <select name="is_employed" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                    <option value="">Select Status</option>
                    <option value="1" @selected(old('is_employed') == '1')>Employed</option>
                    <option value="0" @selected(old('is_employed') == '0')>Not Employed</option>
                </select>
                @error('is_employed')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Payday (Day of month they usually receive money)</label>
                <select name="payday" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                    <option value="">Select Day</option>
                    @for ($day = 1; $day <= 31; $day++)
                        <option value="{{ $day }}" @selected(old('payday') == $day)>{{ $day }}{{ $day == 1 || $day == 21 || $day == 31 ? 'st' : ($day == 2 || $day == 22 ? 'nd' : ($day == 3 || $day == 23 ? 'rd' : 'th')) }}</option>
                    @endfor
                </select>
                @error('payday')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs {{ $helpClass }}">Select the day of the month (1-31) they typically receive their income</p>
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Gross Salary</label>
                <input type="number" name="gross_salary" value="{{ old('gross_salary') }}" step="0.01" min="0" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="0.00">
                @error('gross_salary')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-sm font-medium {{ $labelClass }}">Net Salary <span class="{{ $requiredClass }}">*</span></label>
                <input type="number" name="net_salary" value="{{ old('net_salary') }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="0.00">
                @error('net_salary')
                    <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

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
