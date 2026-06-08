<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Database\Seeder;

class VstRateTableSeeder extends Seeder
{
    public function run(): void
    {
        $loanProduct = LoanProduct::where('code', 'GOV-001')->first();

        if (! $loanProduct) {
            $this->command?->error('Loan product GOV-001 not found. Run LoanProductSeeder first.');

            return;
        }

        $rateType = LoanRateType::updateOrCreate(
            ['code' => 'VST-TERM-RATES'],
            [
                'loan_product_id' => $loanProduct->id,
                'name' => 'VST Standard Term Rates',
                'description' => 'Government business term percentage rates from the VST rate table.',
                'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
                'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
                'accrual_period' => LoanRateType::ACCRUAL_PERIOD_DAILY,
                'is_active' => true,
            ]
        );

        $rates = [
            ['tenure_months' => 1, 'term_interest_percentage' => 27.8],
            ['tenure_months' => 3, 'term_interest_percentage' => 37.1786],
            ['tenure_months' => 6, 'term_interest_percentage' => 46.4211],
            ['tenure_months' => 9, 'term_interest_percentage' => 57.1950],
            ['tenure_months' => 12, 'term_interest_percentage' => 68.6340],
            ['tenure_months' => 18, 'term_interest_percentage' => 92.0558],
            ['tenure_months' => 24, 'term_interest_percentage' => 114.7096],
            ['tenure_months' => 30, 'term_interest_percentage' => 138.6531],
            ['tenure_months' => 36, 'term_interest_percentage' => 161.0768],
        ];

        foreach ($rates as $rate) {
            $termDays = $rate['tenure_months'] * 30;
            $derivedDailyRate = round(
                ($rate['term_interest_percentage'] / 100) / $termDays,
                8
            );

            LoanRate::updateOrCreate(
                [
                    'loan_rate_type_id' => $rateType->id,
                    'tenure_months' => $rate['tenure_months'],
                    'min_principal' => null,
                    'max_principal' => null,
                ],
                [
                    'processing_fee_percentage' => 5,
                    'term_interest_percentage' => $rate['term_interest_percentage'],
                    'daily_rate' => null,
                    'weekly_rate' => null,
                    'derived_daily_rate' => $derivedDailyRate,
                    'arrear_rate' => 0.01,
                    'is_active' => true,
                ]
            );
        }

        $defaultGroup = CustomerGroup::query()
            ->where('loan_product_id', $loanProduct->id)
            ->where('code', 'GOV-DEFAULT')
            ->first();

        if ($defaultGroup) {
            $maxConfiguredTenure = collect($rates)->max('tenure_months');

            $defaultGroup->update([
                'loan_rate_type_id' => $rateType->id,
                'max_loan_tenure_months' => $maxConfiguredTenure,
            ]);
        }

        $this->command?->info('VST government term rate tables seeded successfully.');
    }
}
