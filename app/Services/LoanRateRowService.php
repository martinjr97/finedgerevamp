<?php

namespace App\Services;

use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Validation\Rule;

/**
 * Validates and prepares loan rate rows for forms and import.
 *
 * Preview term days for derived_daily_rate on rate rows use tenure_months × 30.
 * Actual loan quotes use LoanPricingService::calculateTermDays(start_date, tenure_months).
 */
class LoanRateRowService
{
    public const PREVIEW_TERM_DAYS_PER_MONTH = 30;

    public function __construct(
        private readonly LoanPricingService $pricing,
    ) {}

    /**
     * Validation rules for loan rate type create/update.
     *
     * @return array<string, mixed>
     */
    public function rateTypeRules(?LoanRateType $existing = null): array
    {
        return [
            'loan_product_id' => 'required|exists:loan_products,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:loan_rate_types,code'.($existing ? ','.$existing->id : ''),
            'description' => 'nullable|string',
            'accrual_period' => 'nullable|in:daily,weekly',
            'interest_behavior' => [
                'nullable',
                Rule::in([
                    LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
                    LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
                ]),
            ],
            'rate_input_mode' => [
                'nullable',
                Rule::in([
                    LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
                    LoanRateType::RATE_INPUT_DAILY_MULTIPLIER,
                    LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
                ]),
            ],
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sync accrual_period with rate_input_mode when not explicitly divergent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeRateTypePayload(array $data): array
    {
        $data['interest_behavior'] = $data['interest_behavior']
            ?? LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT;

        $data['rate_input_mode'] = $data['rate_input_mode']
            ?? LoanRateType::RATE_INPUT_TERM_PERCENTAGE;

        $mode = $data['rate_input_mode'];

        if ($mode === LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER) {
            $data['accrual_period'] = LoanRateType::ACCRUAL_PERIOD_WEEKLY;
        } else {
            $data['accrual_period'] = LoanRateType::ACCRUAL_PERIOD_DAILY;
        }

        return $data;
    }

    public function interestBehaviorLabel(?string $behavior): string
    {
        return match ($behavior) {
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT => 'Upfront flat',
            LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL => 'Daily accrual',
            default => 'Upfront flat',
        };
    }

    public function rateEntryMethodLabel(?string $mode): string
    {
        return match ($mode) {
            LoanRateType::RATE_INPUT_TERM_PERCENTAGE => 'Business term percentage',
            LoanRateType::RATE_INPUT_DAILY_MULTIPLIER => 'Legacy daily rate',
            LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER => 'Legacy weekly rate',
            default => 'Business term percentage',
        };
    }

    public function isLegacyRateEntryMethod(?string $mode): bool
    {
        return in_array($mode, [
            LoanRateType::RATE_INPUT_DAILY_MULTIPLIER,
            LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rateRowRules(LoanRateType $rateType, ?LoanRate $existing = null): array
    {
        $rules = [
            'tenure_months' => [
                'required',
                'integer',
                'min:1',
            ],
            'processing_fee_percentage' => 'required|numeric|min:0|max:100',
            'term_interest_percentage' => 'nullable|numeric|min:0|max:1000',
            'daily_rate' => 'nullable|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'min_principal' => 'nullable|numeric|min:0',
            'max_principal' => 'nullable|numeric|min:0|gte:min_principal',
            'arrear_rate' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];

        $mode = $this->resolveRateInputMode($rateType);

        if ($mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            $rules['term_interest_percentage'] = 'required|numeric|min:0|max:1000';
        } elseif ($mode === LoanRateType::RATE_INPUT_DAILY_MULTIPLIER) {
            $rules['daily_rate'] = 'required|numeric|min:0';
        } elseif ($mode === LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER) {
            $rules['weekly_rate'] = 'required|numeric|min:0';
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareRatePayload(LoanRateType $rateType, array $data): array
    {
        $mode = $this->resolveRateInputMode($rateType);
        $behavior = $rateType->interest_behavior ?? LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL;
        $tenureMonths = (int) $data['tenure_months'];

        $payload = [
            'tenure_months' => $tenureMonths,
            'processing_fee_percentage' => $data['processing_fee_percentage'],
            'term_interest_percentage' => null,
            'daily_rate' => null,
            'weekly_rate' => null,
            'derived_daily_rate' => null,
            'min_principal' => $data['min_principal'] ?? null,
            'max_principal' => $data['max_principal'] ?? null,
            'arrear_rate' => $data['arrear_rate'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            $payload['term_interest_percentage'] = $data['term_interest_percentage'];
            if ($behavior === LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
                $termDays = $this->previewTermDays($tenureMonths);
                $payload['derived_daily_rate'] = $this->pricing->calculateDerivedDailyRateFromTerm(
                    (float) $data['term_interest_percentage'],
                    $termDays
                );
            }
        } elseif ($mode === LoanRateType::RATE_INPUT_DAILY_MULTIPLIER) {
            $payload['daily_rate'] = $data['daily_rate'];
        } elseif ($mode === LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER) {
            $payload['weekly_rate'] = $data['weekly_rate'];
        }

        return $payload;
    }

    public function previewTermDays(int $tenureMonths): int
    {
        return max(1, $tenureMonths * self::PREVIEW_TERM_DAYS_PER_MONTH);
    }

    public function resolveRateInputMode(LoanRateType $rateType): string
    {
        if ($rateType->rate_input_mode) {
            return $rateType->rate_input_mode;
        }

        return $rateType->accrual_period === LoanRateType::ACCRUAL_PERIOD_WEEKLY
            ? LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER
            : LoanRateType::RATE_INPUT_DAILY_MULTIPLIER;
    }

    public function bandKey(int $tenureMonths, mixed $minPrincipal, mixed $maxPrincipal): string
    {
        $minKey = ($minPrincipal === null || $minPrincipal === '') ? 'open' : (string) $minPrincipal;
        $maxKey = ($maxPrincipal === null || $maxPrincipal === '') ? 'open' : (string) $maxPrincipal;

        return "{$tenureMonths}|{$minKey}|{$maxKey}";
    }

    public function findExistingRate(LoanRateType $rateType, array $row): ?LoanRate
    {
        $query = LoanRate::withTrashed()
            ->where('loan_rate_type_id', $rateType->id)
            ->where('tenure_months', (int) $row['tenure_months']);

        $min = $row['min_principal'] ?? null;
        $max = $row['max_principal'] ?? null;

        if ($min === null || $min === '') {
            $query->whereNull('min_principal');
        } else {
            $query->where('min_principal', $min);
        }

        if ($max === null || $max === '') {
            $query->whereNull('max_principal');
        } else {
            $query->where('max_principal', $max);
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function assertUniqueBand(LoanRateType $rateType, array $row, ?int $ignoreRateId = null): void
    {
        $query = LoanRate::withTrashed()
            ->where('loan_rate_type_id', $rateType->id)
            ->where('tenure_months', (int) $row['tenure_months']);

        $min = $row['min_principal'] ?? null;
        $max = $row['max_principal'] ?? null;

        if ($min === null || $min === '') {
            $query->whereNull('min_principal');
        } else {
            $query->where('min_principal', $min);
        }

        if ($max === null || $max === '') {
            $query->whereNull('max_principal');
        } else {
            $query->where('max_principal', $max);
        }

        if ($ignoreRateId !== null) {
            $query->where('id', '!=', $ignoreRateId);
        }

        if ($query->exists()) {
            $bandLabel = $this->formatBandLabel($min, $max);
            throw new \InvalidArgumentException(
                "A rate row already exists for tenure {$row['tenure_months']} months{$bandLabel}."
            );
        }
    }

    /**
     * Import validation rules for a normalized row.
     *
     * @return array<string, mixed>
     */
    public function importRowRules(LoanRateType $rateType): array
    {
        $rules = [
            'tenure_months' => ['required', 'integer', 'min:1'],
            'processing_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'term_interest_percentage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'weekly_rate' => ['nullable', 'numeric', 'min:0'],
            'min_principal' => ['nullable', 'numeric', 'min:0'],
            'max_principal' => ['nullable', 'numeric', 'min:0'],
            'arrear_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];

        $mode = $this->resolveRateInputMode($rateType);

        if ($mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            $rules['term_interest_percentage'] = ['required', 'numeric', 'min:0', 'max:1000'];
        } elseif ($mode === LoanRateType::RATE_INPUT_DAILY_MULTIPLIER) {
            $rules['daily_rate'] = ['required', 'numeric', 'min:0'];
        } elseif ($mode === LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER) {
            $rules['weekly_rate'] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function prepareImportRow(LoanRateType $rateType, array $normalized): array
    {
        if ($normalized['arrear_rate'] === null) {
            $normalized['arrear_rate'] = 0;
        }

        return $this->prepareRatePayload($rateType, $normalized);
    }

    /**
     * @return list<string>
     */
    public function importTemplateHeadings(LoanRateType $rateType): array
    {
        return [
            'tenure_months',
            'processing_fee_percentage',
            'term_interest_percentage',
            'daily_rate',
            'weekly_rate',
            'min_principal',
            'max_principal',
            'arrear_rate',
            'is_active',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function importTemplateSampleRows(LoanRateType $rateType): array
    {
        $mode = $this->resolveRateInputMode($rateType);

        $base = [
            'tenure_months' => 1,
            'processing_fee_percentage' => 5.00,
            'term_interest_percentage' => null,
            'daily_rate' => null,
            'weekly_rate' => null,
            'min_principal' => null,
            'max_principal' => null,
            'arrear_rate' => 0.01,
            'is_active' => 1,
        ];

        if ($mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            return [
                array_merge($base, [
                    'term_interest_percentage' => 27.8,
                ]),
                array_merge($base, [
                    'tenure_months' => 3,
                    'processing_fee_percentage' => 5.00,
                    'term_interest_percentage' => 45.0,
                    'min_principal' => 1000,
                    'max_principal' => 5000,
                ]),
            ];
        }

        if ($mode === LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER) {
            return [
                array_merge($base, ['weekly_rate' => 0.05000]),
                array_merge($base, ['tenure_months' => 2, 'weekly_rate' => 0.05500]),
            ];
        }

        return [
            array_merge($base, ['daily_rate' => 0.03000]),
            array_merge($base, ['tenure_months' => 2, 'daily_rate' => 0.03500]),
        ];
    }

    /**
     * @return list<list<string>>
     */
    public function importInstructionsRows(LoanRateType $rateType): array
    {
        $mode = $this->resolveRateInputMode($rateType);
        $behavior = $rateType->interest_behavior ?? LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL;

        $rows = [
            ['Instructions for importing loan rates'],
            [''],
            ['Rate type', $rateType->name.' ('.$rateType->code.')'],
            ['Interest behavior', $behavior],
            ['Rate input mode', $mode],
            [''],
            ['General rules'],
            ['- tenure_months and processing_fee_percentage are always required'],
            ['- processing_fee_percentage is separate from interest (e.g. interest 27.8%, fee 5%)'],
            ['- arrear_rate defaults to 0 if left blank'],
            ['- min_principal / max_principal are optional amount bands'],
            ['- Only one row per tenure when both min and max principal are empty'],
            [''],
            ['Derived daily rate preview uses tenure_months × '.self::PREVIEW_TERM_DAYS_PER_MONTH.' days'],
            ['Actual loan quotes use calendar months via LoanPricingService'],
            [''],
        ];

        if ($mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            $rows[] = ['For term_percentage mode: fill term_interest_percentage (e.g. 27.8 for 27.8%)'];
            $rows[] = ['Leave daily_rate and weekly_rate empty'];
            if ($behavior === LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
                $rows[] = ['derived_daily_rate is calculated automatically on import'];
            }
        } elseif ($mode === LoanRateType::RATE_INPUT_DAILY_MULTIPLIER) {
            $rows[] = ['For daily_multiplier mode: fill daily_rate; leave term_interest_percentage empty'];
        } else {
            $rows[] = ['For weekly_multiplier mode: fill weekly_rate; leave term_interest_percentage empty'];
        }

        return $rows;
    }

    private function formatBandLabel(mixed $min, mixed $max): string
    {
        if ($min === null && $max === null) {
            return '';
        }

        return ' with principal band '.($min ?? 'open').'–'.($max ?? 'open');
    }
}
