<?php

if (!function_exists('formColors')) {
    /**
     * Get form color classes based on configuration
     * 
     * @return array
     */
    function formColors(): array
    {
        $preset = config('forms.active_preset', 'blue');
        $presetColors = config("forms.presets.{$preset}", config('forms.presets.blue'));
        $colors = array_merge(config('forms.colors', []), $presetColors);
        
        return [
            'section' => "border-{$colors['section_border']} bg-{$colors['section_background']}",
            'input' => "bg-{$colors['input_background']} border-{$colors['input_border']}",
            'inputFocus' => "focus:border-{$colors['input_focus_border']} focus:ring-{$colors['input_focus_ring']}",
            'label' => "text-{$colors['label']}",
            'heading' => "text-{$colors['heading']}",
            'error' => "text-{$colors['error']}",
            'help' => "text-{$colors['help']}",
            'required' => "text-{$colors['required']}",
        ];
    }
}

