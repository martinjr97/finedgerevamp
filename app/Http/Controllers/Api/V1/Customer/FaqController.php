<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Get FAQs for authenticated customers
     */
    public function index(Request $request): JsonResponse
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->whereIn('visibility', [Faq::VISIBILITY_AUTHENTICATED, Faq::VISIBILITY_BOTH])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\V1\FaqResource::collection($faqs),
        ]);
    }
}

