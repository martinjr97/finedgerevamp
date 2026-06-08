<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * Toggle theme between light and dark mode.
     */
    public function toggle(Request $request)
    {
        $theme = $request->input('theme', 'light');
        
        if (!in_array($theme, ['light', 'dark'])) {
            $theme = 'light';
        }

        session(['theme' => $theme]);

        return response()->json(['theme' => $theme]);
    }
}

