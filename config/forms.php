<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Form Color Scheme
    |--------------------------------------------------------------------------
    |
    | Configure the color scheme for customer forms. You can easily switch
    | between different color themes by changing these values.
    |
    | Available color options:
    | - Primary: Main accent color for borders, focus states, and highlights
    | - Primary Ring: Ring color for focus states (with opacity)
    | - Section Border: Border color for form sections
    | - Section Background: Background color for form sections
    | - Input Background: Background color for input fields
    | - Input Border: Border color for input fields
    | - Input Focus: Border color when input is focused
    | - Label: Text color for labels
    | - Heading: Text color for section headings
    | - Error: Text color for error messages
    | - Help: Text color for help text
    |
    */

    'colors' => [
        // Primary accent color (used for focus states, highlights)
        'primary' => 'blue', // Options: blue, cyan, indigo, purple, pink, emerald, etc.
        
        // Primary color shades
        'primary_shade' => '500', // Options: 300, 400, 500, 600, 700
        
        // Section styling
        'section_border' => 'blue-500/30', // More visible blue border
        'section_background' => 'blue-950/30', // Subtle blue background
        
        // Input styling
        'input_background' => 'white/10',
        'input_border' => 'blue-400/40', // Visible blue border
        'input_focus_border' => 'blue-500', // Will be combined with primary color
        'input_focus_ring' => 'blue-500/40', // Will be combined with primary color
        
        // Text colors
        'label' => 'slate-300',
        'heading' => 'white',
        'error' => 'rose-400',
        'help' => 'slate-400',
        'required' => 'rose-400',
        'button_primary' => 'blue-500',
        'button_secondary' => 'blue-600',
        'button_shadow' => 'blue-500/30',
    ],

    /*
    |--------------------------------------------------------------------------
    | Color Presets
    |--------------------------------------------------------------------------
    |
    | Pre-defined color schemes that can be easily switched.
    | Change the 'active_preset' to switch themes.
    |
    */

    'active_preset' => 'blue', // Options: blue, cyan, indigo, purple, emerald, teal

    'presets' => [
        'blue' => [
            'primary' => 'blue',
            'primary_shade' => '500',
            'section_border' => 'blue-500/30',
            'section_background' => 'blue-950/30',
            'input_border' => 'blue-400/40',
            'input_focus_border' => 'blue-500',
            'input_focus_ring' => 'blue-500/40',
            'button_primary' => 'blue-500',
            'button_secondary' => 'blue-600',
            'button_shadow' => 'blue-500/30',
        ],
        'cyan' => [
            'primary' => 'cyan',
            'primary_shade' => '400',
            'input_focus_border' => 'cyan-400',
            'input_focus_ring' => 'cyan-400/40',
            'button_primary' => 'cyan-400',
            'button_secondary' => 'cyan-500',
            'button_shadow' => 'cyan-400/30',
        ],
        'indigo' => [
            'primary' => 'indigo',
            'primary_shade' => '500',
            'input_focus_border' => 'indigo-500',
            'input_focus_ring' => 'indigo-500/40',
            'button_primary' => 'indigo-500',
            'button_secondary' => 'indigo-600',
            'button_shadow' => 'indigo-500/30',
        ],
        'purple' => [
            'primary' => 'purple',
            'primary_shade' => '500',
            'input_focus_border' => 'purple-500',
            'input_focus_ring' => 'purple-500/40',
            'button_primary' => 'purple-500',
            'button_secondary' => 'purple-600',
            'button_shadow' => 'purple-500/30',
        ],
        'emerald' => [
            'primary' => 'emerald',
            'primary_shade' => '500',
            'input_focus_border' => 'emerald-500',
            'input_focus_ring' => 'emerald-500/40',
            'button_primary' => 'emerald-500',
            'button_secondary' => 'emerald-600',
            'button_shadow' => 'emerald-500/30',
        ],
        'teal' => [
            'primary' => 'teal',
            'primary_shade' => '500',
            'input_focus_border' => 'teal-500',
            'input_focus_ring' => 'teal-500/40',
            'button_primary' => 'teal-500',
            'button_secondary' => 'teal-600',
            'button_shadow' => 'teal-500/30',
        ],
    ],
];

