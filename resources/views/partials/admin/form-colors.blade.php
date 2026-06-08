@php
    // Get active preset or use default colors
    $preset = config('forms.active_preset', 'blue');
    $presetColors = config("forms.presets.{$preset}", config('forms.presets.blue'));
    
    // Merge preset with base colors
    $colors = array_merge(config('forms.colors', []), $presetColors);
    
    // Build Tailwind classes
    $classes = [
        'section_border' => "border-{$colors['section_border']}",
        'section_background' => "bg-{$colors['section_background']}",
        'input_background' => "bg-{$colors['input_background']}",
        'input_border' => "border-{$colors['input_border']}",
        'input_focus' => "focus:border-{$colors['input_focus_border']} focus:ring-{$colors['input_focus_ring']}",
        'label' => "text-{$colors['label']}",
        'heading' => "text-{$colors['heading']}",
        'error' => "text-{$colors['error']}",
        'help' => "text-{$colors['help']}",
        'required' => "text-{$colors['required']}",
    ];
@endphp

