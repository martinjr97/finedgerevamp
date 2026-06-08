<?php

namespace App\Http\Controllers;

use App\Models\Faq;

class FaqController extends Controller
{
    /**
     * Public FAQ page (for guests / login page).
     */
    public function public(): \Illuminate\View\View
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->whereIn('visibility', [Faq::VISIBILITY_PUBLIC, Faq::VISIBILITY_BOTH])
            ->orderBy('created_at')
            ->get();

        return view('public.faq', compact('faqs'));
    }

    /**
     * FAQ page for authenticated customers.
     */
    public function customer(): \Illuminate\View\View
    {
        abort_unless(auth('customer')->check(), 403);

        $faqs = Faq::query()
            ->where('is_active', true)
            ->whereIn('visibility', [Faq::VISIBILITY_AUTHENTICATED, Faq::VISIBILITY_BOTH])
            ->orderBy('created_at')
            ->get();

        return view('customer.faq', compact('faqs'));
    }
}


