<form action="{{ route('admin.customers.store') }}" method="POST" class="space-y-8">
    @csrf
    <input type="hidden" name="loan_product_id" value="{{ $product->id }}">
    @if(!empty($registrationRequestId))
        <input type="hidden" name="registration_request_id" value="{{ $registrationRequestId }}">
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold text-white">Group Context</h2>
        <div class="grid gap-6 md:grid-cols-1">
            <div>
                <label class="text-sm font-medium text-slate-300">Group (optional, falls back to Default Group)</label>
                <select name="customer_group_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Use Default Group</option>
                    @foreach ($customerGroups as $group)
                        <option value="{{ $group->id }}" @selected(old('customer_group_id') == $group->id)>
                            {{ $group->name }} ({{ $group->code }})
                        </option>
                    @endforeach
                </select>
                @error('customer_group_id')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold text-white">Bio Data</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-300">First Name <span class="text-red-400">*</span></label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('first_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Last Name <span class="text-red-400">*</span></label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('last_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Email <span class="text-red-400">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Phone</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
                @error('phone')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Referred By</label>
                <select name="referred_by" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">No referral</option>
                    @foreach ($referredByCustomers as $referrer)
                        <option value="{{ $referrer->id }}" @selected(old('referred_by') == $referrer->id)>
                            {{ $referrer->full_name }}{{ $referrer->phone ? ' - '.$referrer->phone : '' }}
                        </option>
                    @endforeach
                </select>
                @error('referred_by')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->subYears(16)->format('Y-m-d') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('date_of_birth')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Gender</label>
                <select name="gender" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select Gender</option>
                    <option value="male" @selected(old('gender') === 'male')>Male</option>
                    <option value="female" @selected(old('gender') === 'female')>Female</option>
                    <option value="other" @selected(old('gender') === 'other')>Other</option>
                </select>
                @error('gender')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
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

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold text-white">Address</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-300">Address Line 1 <span class="text-red-400">*</span></label>
                <input type="text" name="address_line1" value="{{ old('address_line1') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('address_line1')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-300">Address Line 2</label>
                <input type="text" name="address_line2" value="{{ old('address_line2') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('address_line2')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">City <span class="text-red-400">*</span></label>
                <input type="text" name="city" value="{{ old('city') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('city')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Province</label>
                <select name="province_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select Province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
                @error('province_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Postal Code</label>
                <input type="text" name="postal_code" value="{{ old('postal_code') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('postal_code')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Country <span class="text-red-400">*</span></label>
                <input type="text" name="country" value="{{ old('country', 'Zambia') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('country')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
        <h2 class="mb-6 text-xl font-semibold text-white">Employment / Business Details</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-300">Occupation Type <span class="text-red-400">*</span></label>
                <select name="occupation_type" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select Type</option>
                    <option value="employed" @selected(old('occupation_type') === 'employed')>Employed</option>
                    <option value="business_owner" @selected(old('occupation_type') === 'business_owner')>Business Owner</option>
                </select>
                @error('occupation_type')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Employer / Business Name <span class="text-red-400">*</span></label>
                <input type="text" name="employer_or_business_name" value="{{ old('employer_or_business_name') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('employer_or_business_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Average Income <span class="text-red-400">*</span></label>
                <input type="number" name="average_income" value="{{ old('average_income') }}" step="0.01" min="0" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="0.00">
                @error('average_income')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-300">Business Location Address Line 1 <span class="text-red-400">*</span></label>
                <input type="text" name="work_address_line1" value="{{ old('work_address_line1') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('work_address_line1')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-300">Business Location Address Line 2</label>
                <input type="text" name="work_address_line2" value="{{ old('work_address_line2') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('work_address_line2')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Business Location City <span class="text-red-400">*</span></label>
                <input type="text" name="work_city" value="{{ old('work_city') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('work_city')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Business Location Province</label>
                <select
                    name="work_province_id"
                    data-province-select
                    data-province-district-pair="work"
                    data-no-select-search="true"
                    class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                >
                    <option value="">Select Province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected(old('work_province_id') == $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
                @error('work_province_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Business Location District</label>
                <select
                    name="work_district_id"
                    data-district-select
                    data-province-district-pair="work"
                    data-placeholder="Select District"
                    data-no-select-search="true"
                    class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                >
                    <option value="">Select District</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" data-province-id="{{ $district->province_id }}" @selected(old('work_district_id') == $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
                @error('work_district_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Business Location Postal Code</label>
                <input type="text" name="work_postal_code" value="{{ old('work_postal_code') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('work_postal_code')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Business Location Country <span class="text-red-400">*</span></label>
                <input type="text" name="work_country" value="{{ old('work_country', 'Zambia') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                @error('work_country')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            Create Customer
        </button>
        <a href="{{ route('admin.customers.select-product-type') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
    </div>
</form>

@include('partials.province-district-cascade')
