<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanProductPublicWebsiteService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateSettings(LoanProduct $loanProduct, array $payload): LoanProduct
    {
        $isPublic = (bool) ($payload['is_public_on_website'] ?? false);
        $rateTypeId = $payload['public_website_loan_rate_type_id'] ?? null;
        /** @var list<int|string> $rateIds */
        $rateIds = $payload['public_website_loan_rate_ids'] ?? [];

        if (! $isPublic) {
            return DB::transaction(function () use ($loanProduct) {
                $loanProduct->publicWebsiteRates()->detach();
                $loanProduct->update([
                    'is_public_on_website' => false,
                    'public_website_loan_rate_type_id' => null,
                ]);

                return $loanProduct->fresh(['publicWebsiteRateType', 'publicWebsiteRates']);
            });
        }

        if ($rateTypeId === null) {
            throw ValidationException::withMessages([
                'public_website_loan_rate_type_id' => 'Select a product type (rate type) for website quotes.',
            ]);
        }

        $rateType = LoanRateType::query()
            ->whereKey($rateTypeId)
            ->where('loan_product_id', $loanProduct->id)
            ->where('is_active', true)
            ->first();

        if ($rateType === null) {
            throw ValidationException::withMessages([
                'public_website_loan_rate_type_id' => 'The selected product type does not belong to this loan product.',
            ]);
        }

        $normalizedRateIds = collect($rateIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($normalizedRateIds->isEmpty()) {
            throw ValidationException::withMessages([
                'public_website_loan_rate_ids' => 'Select at least one rate row for the website calculator.',
            ]);
        }

        $validRateIds = LoanRate::query()
            ->where('loan_rate_type_id', $rateType->id)
            ->where('is_active', true)
            ->whereIn('id', $normalizedRateIds)
            ->pluck('id');

        if ($validRateIds->count() !== $normalizedRateIds->count()) {
            throw ValidationException::withMessages([
                'public_website_loan_rate_ids' => 'One or more selected rates are invalid for this product type.',
            ]);
        }

        return DB::transaction(function () use ($loanProduct, $rateType, $validRateIds) {
            $loanProduct->update([
                'is_public_on_website' => true,
                'public_website_loan_rate_type_id' => $rateType->id,
            ]);

            $loanProduct->publicWebsiteRates()->sync($validRateIds->all());

            $rateType->update(['is_public_on_website' => true]);

            return $loanProduct->fresh(['publicWebsiteRateType', 'publicWebsiteRates']);
        });
    }
}
