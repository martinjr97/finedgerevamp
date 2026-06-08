@extends('layouts.admin')

@section('title', 'Create Market | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Market',
            'description' => 'Add a new market for the Marketeer loan product',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Markets',
                    'href' => route('admin.markets.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

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

        <form action="{{ route('admin.markets.store') }}" method="POST" class="space-y-8">
            @csrf

            {{-- Market Information --}}
            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Market Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Market Name <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('name')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Code <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="code" value="{{ old('code') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., MKT-LUSAKA-001">
                        @error('code')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Unique code for this market</p>
                    </div>
                </div>
            </div>

            {{-- Address Information --}}
            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Address Information</h2>
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
                        <label class="text-sm font-medium {{ $labelClass }}">City</label>
                        <input type="text" name="city" value="{{ old('city') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('city')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Province <span class="{{ $requiredClass }}">*</span></label>
                        <select name="province_id" id="province_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Province</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>{{ $province->name }}</option>
                            @endforeach
                        </select>
                        @error('province_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">District <span class="{{ $requiredClass }}">*</span></label>
                        <select name="district_id" id="district_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select District</option>
                            @foreach($districts as $district)
                                <option value="{{ $district->id }}" data-province-id="{{ $district->province_id }}" @selected(old('district_id') == $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                        @error('district_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Contact Information --}}
            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Contact Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Contact Person Name (Market Manager) <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="contact_person_name" value="{{ old('contact_person_name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('contact_person_name')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Contact Person Phone <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="contact_person_phone" value="{{ old('contact_person_phone') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="+260...">
                        @error('contact_person_phone')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Contact Person Email</label>
                        <input type="email" name="contact_person_email" value="{{ old('contact_person_email') }}" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('contact_person_email')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Portfolio Manager</label>
                        <select name="portfolio_manager_id" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Portfolio Manager</option>
                            @foreach($portfolioManagers as $manager)
                                <option value="{{ $manager->id }}" @selected(old('portfolio_manager_id') == $manager->id)>
                                    {{ $manager->first_name }} {{ $manager->last_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('portfolio_manager_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Select a relationship manager from admins</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Branch <span class="{{ $requiredClass }}">*</span></label>
                        <select name="branch_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Branch</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Select the branch responsible for this market</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Interest Rate Type</label>
                        <select name="loan_rate_type_id" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Interest Rate Type</option>
                            @foreach($loanRateTypes ?? [] as $rateType)
                                <option value="{{ $rateType->id }}" @selected(old('loan_rate_type_id') == $rateType->id)>
                                    {{ $rateType->name }} ({{ $rateType->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('loan_rate_type_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">All customers linked to this market will use this interest rate type</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Status</label>
                        <select name="is_active" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="1" @selected(old('is_active', true) == true)>Active</option>
                            <option value="0" @selected(old('is_active') == false)>Inactive</option>
                        </select>
                        @error('is_active')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.markets.index') }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
                    Create Market
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        // Filter districts based on selected province
        document.getElementById('province_id').addEventListener('change', function() {
            const provinceId = this.value;
            const districtSelect = document.getElementById('district_id');
            const options = districtSelect.querySelectorAll('option');
            
            // Reset district selection
            districtSelect.value = '';
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                } else {
                    const optionProvinceId = option.getAttribute('data-province-id');
                    if (optionProvinceId === provinceId) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });
        });
    </script>
    @endpush
@endsection

