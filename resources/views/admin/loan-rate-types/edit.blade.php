@extends('layouts.admin')

@section('title', 'Edit Loan Rate Type | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Loan Rate Type',
            'description' => 'Update rate type information',
            'buttons' => array_filter([
                auth('admin')->user()?->can('loan-rate-types.view') ? [
                    'action' => 'secondary',
                    'text' => 'Back',
                    'href' => route('admin.loan-rate-types.show', $loanRateType),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ] : null,
            ])
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

        <form action="{{ route('admin.loan-rate-types.update', $loanRateType) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Rate Type Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Loan Product <span class="{{ $requiredClass }}">*</span></label>
                        <select name="loan_product_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="">Select Loan Product</option>
                            @foreach($loanProducts as $product)
                                <option value="{{ $product->id }}" @selected(old('loan_product_id', $loanRateType->loan_product_id) == $product->id)>
                                    {{ $product->name }} ({{ $product->code }}) - {{ ucfirst($product->category) }}
                                </option>
                            @endforeach
                        </select>
                        @error('loan_product_id')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Name <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $loanRateType->name) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('name')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Code <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="code" value="{{ old('code', $loanRateType->code) }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        @error('code')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    @include('admin.loan-rate-types.partials.pricing-mode-fields')
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium {{ $labelClass }}">Description</label>
                        <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">{{ old('description', $loanRateType->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Status</label>
                        <select name="is_active" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                            <option value="1" @selected(old('is_active', $loanRateType->is_active) == true)>Active</option>
                            <option value="0" @selected(old('is_active', $loanRateType->is_active) == false)>Inactive</option>
                        </select>
                        @error('is_active')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.loan-rate-types.show', $loanRateType) }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
                    Update Rate Type
                </button>
            </div>
        </form>
    </div>
@endsection

