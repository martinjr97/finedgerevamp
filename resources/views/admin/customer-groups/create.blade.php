@extends('layouts.admin')

@section('title', 'Create Customer Group | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Customer Group',
            'description' => 'Product: ' . $loanProduct->name . ' (' . $loanProduct->code . ')',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-products.show', $loanProduct),
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

        <form action="{{ route('admin.customer-groups.store') }}" method="POST" class="space-y-8">
            @csrf
            <input type="hidden" name="loan_product_id" value="{{ $loanProduct->id }}">

            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Group Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Group Name <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('name')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Code <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="code" value="{{ old('code') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., CHAR-TRUSTED">
                        @error('code')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Unique code for this customer group</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium {{ $labelClass }}">Description</label>
                        <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Risk Level <span class="{{ $requiredClass }}">*</span></label>
                        <select name="risk_level" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Risk Level</option>
                            <option value="low" @selected(old('risk_level') == 'low')>Low</option>
                            <option value="medium" @selected(old('risk_level') == 'medium')>Medium</option>
                            <option value="high" @selected(old('risk_level') == 'high')>High</option>
                        </select>
                        @error('risk_level')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Branch <span class="{{ $requiredClass }}">*</span></label>
                        <select name="branch_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Branch</option>
                            @foreach ($branches ?? [] as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Select the branch responsible for this customer group</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Relationship Manager</label>
                        <select name="relationship_manager_id" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Relationship Manager</option>
                            @foreach ($relationshipManagers ?? [] as $manager)
                                <option value="{{ $manager->id }}" @selected(old('relationship_manager_id') == $manager->id)>
                                    {{ $manager->full_name }} ({{ $manager->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('relationship_manager_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Optional: assign a relationship manager responsible for this group</p>
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
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Max Loan Amount</label>
                        <input type="number" name="max_loan_amount" value="{{ old('max_loan_amount') }}" step="1" min="0" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="0">
                        @error('max_loan_amount')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Maximum loan amount for this group (optional)</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Max Loan Tenure (Months)</label>
                        <input type="number" name="max_loan_tenure_months" value="{{ old('max_loan_tenure_months') }}" step="1" min="1" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="0">
                        @error('max_loan_tenure_months')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Maximum loan tenure in months (optional)</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="allow_multiple_loans" value="0">
                            <input type="checkbox"
                                   name="allow_multiple_loans"
                                   value="1"
                                   @checked(old('allow_multiple_loans', false))
                                   class="mt-1 rounded border-white/20 bg-slate-800 text-cyan-500 focus:ring-cyan-500/40">
                            <span>
                                <span class="text-sm font-medium {{ $labelClass }}">Allow multiple active loans</span>
                                <span class="mt-1 block text-xs {{ $helpClass }}">If disabled, customers in this group can only have one pending, approved, or active loan at a time.</span>
                            </span>
                        </label>
                        @error('allow_multiple_loans')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Interest Rate Type</label>
                        <select name="loan_rate_type_id" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Interest Rate Type</option>
                            @foreach ($loanRateTypes ?? [] as $rateType)
                                <option value="{{ $rateType->id }}" @selected(old('loan_rate_type_id') == $rateType->id)>
                                    {{ $rateType->name }} ({{ $rateType->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('loan_rate_type_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">All customers in this group will use this interest rate type</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.loan-products.show', $loanProduct) }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
                    Create Group
                </button>
            </div>
        </form>
    </div>
@endsection

