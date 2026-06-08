@extends('layouts.admin')

@section('title', 'Edit Upload Record | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Upload Record',
            'description' => 'Correct the data and retry customer creation',
        ])

        {{-- Error Message --}}
        @if($record->error_message)
            <div class="rounded-3xl border border-rose-500/40 bg-gradient-to-r from-rose-500/25 to-rose-500/15 p-4 shadow-lg shadow-rose-500/10">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-rose-300 mb-1">Error Message:</p>
                        <p class="text-sm text-rose-200">{{ $record->error_message }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Edit Form --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="POST" action="{{ route('admin.customers.upload-record.update', $record) }}">
                @csrf
                @method('POST')

                <div class="space-y-6">
                    {{-- Basic Information --}}
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-4">Basic Information</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">First Name <span class="text-red-400">*</span></label>
                                <input type="text" name="first_name" value="{{ $record->data['first name'] ?? $record->data['first_name'] ?? '' }}" required
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Last Name <span class="text-red-400">*</span></label>
                                <input type="text" name="last_name" value="{{ $record->data['last name'] ?? $record->data['last_name'] ?? '' }}" required
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Email <span class="text-red-400">*</span></label>
                                <input type="email" name="email" value="{{ $record->data['email'] ?? '' }}" required
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Phone</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" maxlength="12" inputmode="numeric" pattern="260[0-9]{9}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 zambian-phone-input" placeholder="260978232334">
data['phone'] ?? '' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">National ID <span class="text-red-400">*</span></label>
                                <input type="text" name="national_id" value="{{ $record->data['national id'] ?? $record->data['national_id'] ?? '' }}" required
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">TPIN <span class="text-red-400">*</span></label>
                                <input type="text" name="tpin" value="{{ $record->data['tpin'] ?? '' }}" required
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Date of Birth</label>
                                <input type="date" name="date_of_birth" value="{{ $record->data['date of birth'] ?? $record->data['date_of_birth'] ?? $record->data['dob'] ?? '' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Gender</label>
                                <select name="gender" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                    <option value="">Select Gender</option>
                                    <option value="male" @selected(($record->data['gender'] ?? '') === 'male')>Male</option>
                                    <option value="female" @selected(($record->data['gender'] ?? '') === 'female')>Female</option>
                                    <option value="other" @selected(($record->data['gender'] ?? '') === 'other')>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Address Information --}}
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-4">Address Information</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Address Line 1</label>
                                <input type="text" name="address_line1" value="{{ $record->data['address line 1'] ?? $record->data['address_line1'] ?? $record->data['address'] ?? '' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Address Line 2</label>
                                <input type="text" name="address_line2" value="{{ $record->data['address line 2'] ?? $record->data['address_line2'] ?? '' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">City</label>
                                <input type="text" name="city" value="{{ $record->data['city'] ?? '' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Province</label>
                                <select name="province" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                    <option value="">Select Province</option>
                                    @foreach($provinces as $province)
                                        <option value="{{ $province->name }}" @selected(($record->data['province'] ?? '') === $province->name)>
                                            {{ $province->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Postal Code</label>
                                <input type="text" name="postal_code" value="{{ $record->data['postal code'] ?? $record->data['postal_code'] ?? '' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Country</label>
                                <input type="text" name="country" value="{{ $record->data['country'] ?? 'Zambia' }}"
                                       class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                            </div>
                        </div>
                    </div>

                    {{-- Product-Specific Fields --}}
                    @if($product->category === 'government')
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">Government Employee Information</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Ministry</label>
                                    <select name="ministry" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                        <option value="">Select Ministry</option>
                                        @foreach($ministries as $ministry)
                                            <option value="{{ $ministry->name }}" @selected(($record->data['ministry'] ?? '') === $ministry->name)>
                                                {{ $ministry->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Date of Employment</label>
                                    <input type="date" name="date_of_employment" value="{{ $record->data['date of employment'] ?? $record->data['date_of_employment'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Gross Salary</label>
                                    <input type="number" step="0.01" name="gross_salary" value="{{ $record->data['gross salary'] ?? $record->data['gross_salary'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Net Salary</label>
                                    <input type="number" step="0.01" name="net_salary" value="{{ $record->data['net salary'] ?? $record->data['net_salary'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                            </div>
                        </div>
                    @elseif($product->category === 'mou')
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">MOU Employee Information</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Company</label>
                                    <select name="company" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                        <option value="">Select Company</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->name }}" @selected(($record->data['company'] ?? '') === $company->name)>
                                                {{ $company->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Position</label>
                                    <input type="text" name="position" value="{{ $record->data['position'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Gross Salary</label>
                                    <input type="number" step="0.01" name="gross_salary" value="{{ $record->data['gross salary'] ?? $record->data['gross_salary'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Net Salary</label>
                                    <input type="number" step="0.01" name="net_salary" value="{{ $record->data['net salary'] ?? $record->data['net_salary'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                            </div>
                        </div>
                    @elseif($product->category === 'character' || $product->category === 'collateral')
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">Customer Group & Employment</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                @if($product->category === 'character')
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-2">Customer Group</label>
                                        <select name="customer_group" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                            <option value="">Select Customer Group</option>
                                            @foreach($customerGroups as $group)
                                                <option value="{{ $group->name }}" @selected(($record->data['customer group'] ?? $record->data['customer_group'] ?? $record->data['group'] ?? '') === $group->name)>
                                                    {{ $group->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Gross Salary</label>
                                    <input type="number" step="0.01" name="gross_salary" value="{{ $record->data['gross salary'] ?? $record->data['gross_salary'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Net Salary</label>
                                    <input type="number" step="0.01" name="net_salary" value="{{ $record->data['net salary'] ?? $record->data['net_salary'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                            </div>
                        </div>
                    @elseif($product->category === 'marketeer')
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4">Marketeer Information</h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Company</label>
                                    <select name="company" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                        <option value="">Select Company</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->name }}" @selected(($record->data['company'] ?? '') === $company->name)>
                                                {{ $company->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Market</label>
                                    <select name="market" class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                        <option value="">Select Market</option>
                                        @foreach($markets as $market)
                                            <option value="{{ $market->name }}" @selected(($record->data['market'] ?? '') === $market->name)>
                                                {{ $market->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-2">Monthly Income</label>
                                    <input type="number" step="0.01" name="monthly_income" value="{{ $record->data['monthly income'] ?? $record->data['monthly_income'] ?? '' }}"
                                           class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition">
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Form Actions --}}
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                        <a href="{{ route('admin.customers.upload-batch.show', $batch) }}" 
                           class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

