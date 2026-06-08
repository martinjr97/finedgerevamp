@extends('layouts.admin')

@section('title', 'Edit Loan Rate | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Loan Rate',
            'description' => 'Rate Type: ' . $loanRateType->name . ' (' . $loanRateType->code . ')',
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

        <form action="{{ route('admin.loan-rate-types.rates.update', [$loanRateType, $loanRate]) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold {{ $headingClass }} flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-{{ $colors['input_focus_border'] }}"></span>Rate Information</h2>
                @include('admin.loan-rate-types.rates.partials.fields')
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.loan-rate-types.show', $loanRateType) }}" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonSecondaryClass }} px-4 py-3 text-sm font-semibold transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl {{ $buttonPrimaryClass }} px-4 py-3 font-semibold text-slate-900 shadow-lg">
                    Update Rate
                </button>
            </div>
        </form>
    </div>
@endsection

