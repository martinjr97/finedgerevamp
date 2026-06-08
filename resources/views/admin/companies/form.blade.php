@php
    $isEdit = isset($company) && $company && $company->exists;
    $sectors = $sectors ?? collect();
    $relationshipManagers = $relationshipManagers ?? collect();
    $company = $company ?? null;
@endphp

<form action="{{ $isEdit ? route('admin.companies.update', $company) : route('admin.companies.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div>
                <label class="text-sm font-medium text-slate-300">Name</label>
                <input type="text" name="name" value="{{ old('name', $isEdit ? $company->name : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Code</label>
                <input type="text" name="code" value="{{ old('code', $isEdit ? $company->code : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Status</label>
                <select name="status" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @foreach (['pending', 'active', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $isEdit ? $company->status : 'active') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Registration Number</label>
                <input type="text" name="registration_number" value="{{ old('registration_number', $isEdit ? $company->registration_number : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Company TPIN</label>
                <input type="text" name="tpin" value="{{ old('tpin', $isEdit ? $company->tpin : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="Enter TPIN">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Date of Incorporation</label>
                <input type="date" name="date_of_incorporation" value="{{ old('date_of_incorporation', $isEdit ? $company->date_of_incorporation?->format('Y-m-d') : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">MOU Expiry Date</label>
                <input type="date" name="mou_expiry_date" value="{{ old('mou_expiry_date', $isEdit ? $company->mou_expiry_date?->format('Y-m-d') : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                <p class="mt-1 text-xs text-slate-400">Optional reminder of when the agreement lapses</p>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Sector</label>
                <select name="sector_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select Sector</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('sector_id', $isEdit ? ($company->sector_id ?? '') : '') == $sector->id)>
                            {{ $sector->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div>
                <label class="text-sm font-medium text-slate-300">Relationship Manager</label>
                <select name="relationship_manager_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select Relationship Manager</option>
                    @foreach ($relationshipManagers as $manager)
                        <option value="{{ $manager->id }}" @selected(old('relationship_manager_id', $isEdit ? ($company->relationship_manager_id ?? '') : '') == $manager->id)>
                            {{ $manager->full_name }} ({{ $manager->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Interest Rate Type</label>
                <select name="loan_rate_type_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select Interest Rate Type</option>
                    @foreach ($loanRateTypes ?? [] as $rateType)
                        <option value="{{ $rateType->id }}" @selected(old('loan_rate_type_id', $isEdit ? ($company->loan_rate_type_id ?? '') : '') == $rateType->id)>
                            {{ $rateType->name }} ({{ $rateType->code }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">All customers linked to this company will use this interest rate type</p>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Contact Email</label>
                <input type="email" name="contact_email" value="{{ old('contact_email', $isEdit ? $company->contact_email : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Contact Phone</label>
                <input type="text" name="contact_phone" value="{{ old('contact_phone', $isEdit ? $company->contact_phone : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Address Line 1</label>
                <input type="text" name="address_line1" value="{{ old('address_line1', $isEdit ? $company->address_line1 : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Address Line 2</label>
                <input type="text" name="address_line2" value="{{ old('address_line2', $isEdit ? $company->address_line2 : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-300">City</label>
                    <input type="text" name="city" value="{{ old('city', $isEdit ? $company->city : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">State</label>
                    <input type="text" name="state" value="{{ old('state', $isEdit ? $company->state : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-300">Postal Code</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $isEdit ? $company->postal_code : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">Country</label>
                    <input type="text" name="country" value="{{ old('country', $isEdit ? $company->country : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-500/10 via-transparent to-blue-500/5 p-6 shadow-lg space-y-6 md:col-span-2">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-white">Loan Programme & Limits</h2>
                    <p class="text-sm text-slate-400">Define how much and when this company can transact.</p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs text-cyan-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Auto applied to all customers
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-slate-300">Maximum Loan Tenure (Months)</label>
                    <input type="number" name="maximum_loan_tenure_months" min="1" max="360" value="{{ old('maximum_loan_tenure_months', $isEdit ? $company->maximum_loan_tenure_months : 12) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="e.g. 24">
                    <p class="mt-1 text-xs text-slate-400">Upper limit for repayment plans</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">Monthly Cut-off Day</label>
                    <select name="monthly_cut_off_day" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select day</option>
                        @for ($day = 1; $day <= 31; $day++)
                            <option value="{{ $day }}" @selected(old('monthly_cut_off_day', $isEdit ? $company->monthly_cut_off_day : 25) == $day)>{{ $day }}</option>
                        @endfor
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Loans captured after this day roll into the next month</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">Pay Day</label>
                    <select name="pay_day" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select day</option>
                        @for ($day = 1; $day <= 31; $day++)
                            <option value="{{ $day }}" @selected(old('pay_day', $isEdit ? $company->pay_day : 30) == $day)>{{ $day }}</option>
                        @endfor
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Expected company remittance date</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-slate-300">Maximum Debit Ratio (%)</label>
                    <input type="number" name="maximum_debit_ratio" step="0.01" min="0" max="100" value="{{ old('maximum_debit_ratio', $isEdit ? $company->maximum_debit_ratio : 40) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <p class="mt-1 text-xs text-slate-400">Max percentage of net salary allowed per customer</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">Instalment Cross Over (%)</label>
                    <input type="number" name="instalment_cross_over_percentage" step="0.01" min="0" max="100" value="{{ old('instalment_cross_over_percentage', $isEdit ? $company->instalment_cross_over_percentage : 5) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <p class="mt-1 text-xs text-slate-400">Minimum percentage of net to qualify for instalments</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300">Arrangement Fee (%)</label>
                    <input type="number" name="arrangement_fee_percentage" step="0.01" min="0" max="100" value="{{ old('arrangement_fee_percentage', $isEdit ? $company->arrangement_fee_percentage : 0) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <p class="mt-1 text-xs text-slate-400">Processing fee charged by the company</p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.companies.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Company' : 'Create Company' }}
        </button>
    </div>
</form>
