@extends('layouts.admin')

@section('title', 'Create Channel | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Channel',
            'description' => 'Add a new payment channel for disbursement and/or repayment',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Channels',
                    'href' => route('admin.channels.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        @php
            $preset = config('forms.active_preset', 'blue');
            $presetColors = config("forms.presets.{$preset}", config('forms.presets.blue'));
            $colors = array_merge(config('forms.colors', []), $presetColors);
            
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

        <form action="{{ route('admin.channels.store') }}" method="POST" class="space-y-8">
            @csrf

            {{-- Channel Information --}}
            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Channel Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Channel Name <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., Airtel Money">
                        @error('name')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium {{ $labelClass }}">Code <span class="{{ $requiredClass }}">*</span></label>
                        <input type="text" name="code" value="{{ old('code') }}" required class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="e.g., AIRTEL_MONEY">
                        @error('code')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs {{ $helpClass }}">Unique code for this channel (uppercase, underscores)</p>
                    </div>
                    @include('admin.channels.partials.type-field', [
                        'labelClass' => $labelClass,
                        'helpClass' => $helpClass,
                        'errorClass' => $errorClass,
                        'requiredClass' => $requiredClass,
                        'inputClass' => 'mt-2 w-full rounded-2xl '.$inputClass.' text-white px-4 py-3 '.$inputFocusClass,
                    ])
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium {{ $labelClass }}">Description</label>
                        <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}" placeholder="Optional description of this channel">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Channel Capabilities --}}
            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Channel Capabilities</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="can_disburse" value="0">
                            <input type="checkbox" name="can_disburse" value="1" {{ old('can_disburse', false) ? 'checked' : '' }} class="w-5 h-5 rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-0">
                            <div>
                                <span class="text-sm font-medium {{ $labelClass }}">Can be used for Disbursement <span class="{{ $requiredClass }}">*</span></span>
                                <p class="text-xs {{ $helpClass }} mt-1">Allow this channel to be used for loan disbursements</p>
                            </div>
                        </label>
                        @error('can_disburse')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="can_repay" value="0">
                            <input type="checkbox" name="can_repay" value="1" {{ old('can_repay', false) ? 'checked' : '' }} class="w-5 h-5 rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-0">
                            <div>
                                <span class="text-sm font-medium {{ $labelClass }}">Can be used for Repayment <span class="{{ $requiredClass }}">*</span></span>
                                <p class="text-xs {{ $helpClass }} mt-1">Allow this channel to be used for loan repayments</p>
                            </div>
                        </label>
                        @error('can_repay')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="is_repayment_integrated" value="0">
                            <input type="checkbox" name="is_repayment_integrated" value="1" {{ old('is_repayment_integrated', true) ? 'checked' : '' }} class="w-5 h-5 rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-0">
                            <div>
                                <span class="text-sm font-medium {{ $labelClass }}">Automated Repayment Integration</span>
                                <p class="text-xs {{ $helpClass }} mt-1">When enabled, repayments on this channel are sent for automated processing. Disable for manual approval flow.</p>
                            </div>
                        </label>
                        @error('is_repayment_integrated')
                            <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="mt-4 rounded-2xl border border-cyan-400/20 bg-cyan-500/5 p-4 text-sm text-slate-200">
                    <p class="font-semibold text-white">Note:</p>
                    <p class="mt-1">At least one capability (Disbursement or Repayment) must be enabled for the channel to be functional.</p>
                </div>
            </div>

            {{-- Status --}}
            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Status</h2>
                <div>
                    <label class="text-sm font-medium {{ $labelClass }}">Active Status</label>
                    <select name="is_active" class="mt-2 w-full rounded-2xl {{ $inputClass }} text-white px-4 py-3 {{ $inputFocusClass }}">
                        <option value="1" @selected(old('is_active', true) == true)>Active</option>
                        <option value="0" @selected(old('is_active') == false)>Inactive</option>
                    </select>
                    @error('is_active')
                        <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs {{ $helpClass }}">Inactive channels will not be available for selection in disbursement or repayment forms</p>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.channels.index') }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
                    Create Channel
                </button>
            </div>
        </form>
    </div>
@endsection
