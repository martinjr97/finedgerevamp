<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\PublicWebsiteCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WebsiteIntegrationController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteCatalogService $catalog,
    ) {}

    public function faqs(): JsonResponse
    {
        $items = Faq::query()
            ->where('is_active', true)
            ->whereIn('visibility', [Faq::VISIBILITY_PUBLIC, Faq::VISIBILITY_BOTH])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function loanProducts(): JsonResponse
    {
        $items = $this->catalog->publicLoanProducts()
            ->map(fn ($product) => $this->catalog->serializeLoanProduct($product))
            ->values();

        return response()->json(['data' => $items]);
    }

    /** @deprecated Prefer loanProducts(); retained for older website builds */
    public function loanRateTypes(): JsonResponse
    {
        $items = $this->catalog->publicRateTypes()
            ->map(fn ($type) => $this->catalog->serializeRateType($type))
            ->values();

        return response()->json(['data' => $items]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_product_id' => ['nullable', 'integer', 'min:1', 'required_without:loan_rate_type_id'],
            'loan_rate_type_id' => ['nullable', 'integer', 'min:1', 'required_without:loan_product_id'],
            'principal' => ['required', 'numeric', 'min:1'],
            'tenure_months' => ['required', 'integer', 'min:1', 'max:360'],
        ]);

        try {
            if (! empty($validated['loan_product_id'])) {
                $quote = $this->catalog->quoteForLoanProduct(
                    (int) $validated['loan_product_id'],
                    (float) $validated['principal'],
                    (int) $validated['tenure_months'],
                );
            } else {
                $quote = $this->catalog->quote(
                    (int) $validated['loan_rate_type_id'],
                    (float) $validated['principal'],
                    (int) $validated['tenure_months'],
                );
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $quote]);
    }
}
