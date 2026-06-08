<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanRateRowService;
use App\Services\LoanRateTypeSafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LoanRateTypeController extends Controller
{
    public function __construct(
        private readonly LoanRateRowService $rateRows,
        private readonly LoanRateTypeSafetyService $safety,
    ) {}
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.view'), 403);

        $query = LoanRateType::with(['loanProduct', 'loanRates']);
        
        // Filter by product if provided
        if ($request->has('product_id')) {
            $query->where('loan_product_id', $request->product_id);
        }
        
        $rateTypes = $query->orderBy('name')->get();
        $rateTypeDeletable = $rateTypes->mapWithKeys(
            fn (LoanRateType $type) => [$type->id => $this->safety->assessRateTypeDeletion($type)]
        );

        return view('admin.loan-rate-types.index', compact('rateTypes', 'rateTypeDeletable'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.create'), 403);

        $loanProducts = LoanProduct::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.loan-rate-types.create', compact('loanProducts'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.create'), 403);

        $validated = $request->validate($this->rateRows->rateTypeRules());
        $validated = $this->rateRows->normalizeRateTypePayload($validated);

        try {
            $loanRateType = LoanRateType::create($validated);

            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('status', 'Loan rate type created. Add rate rows below.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-rate-types.create')
                ->withInput()
                ->with('error', 'Failed to create loan rate type: '.$e->getMessage());
        }
    }

    public function show(LoanRateType $loanRateType): View
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.view'), 403);

        $loanRateType->load(['loanProduct', 'loanRates']);
        $targetLoanProducts = LoanProduct::query()
            ->where('is_active', true)
            ->where('id', '!=', $loanRateType->loan_product_id)
            ->orderBy('name')
            ->get();

        $rateRowDeletable = $loanRateType->loanRates
            ->mapWithKeys(fn (LoanRate $rate) => [$rate->id => $this->safety->canDeleteRate($rate)])
            ->all();
        $rateTypeDeletion = $this->safety->assessRateTypeDeletion($loanRateType);

        return view('admin.loan-rate-types.show', compact(
            'loanRateType',
            'targetLoanProducts',
            'rateRowDeletable',
            'rateTypeDeletion',
        ));
    }

    public function edit(LoanRateType $loanRateType): View
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        $loanProducts = LoanProduct::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.loan-rate-types.edit', compact('loanRateType', 'loanProducts'));
    }

    public function update(Request $request, LoanRateType $loanRateType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        $validated = $request->validate($this->rateRows->rateTypeRules($loanRateType));
        $validated = $this->rateRows->normalizeRateTypePayload($validated);

        try {
            $loanRateType->update($validated);

            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('status', 'Loan rate type updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-rate-types.edit', $loanRateType)
                ->withInput()
                ->with('error', 'Failed to update loan rate type: '.$e->getMessage());
        }
    }

    public function destroy(LoanRateType $loanRateType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.delete'), 403);

        try {
            $this->safety->deleteRateType($loanRateType);

            return redirect()
                ->route('admin.loan-rate-types.index')
                ->with('status', 'Loan rate type deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete loan rate type: '.$e->getMessage());
        }
    }

    // Nested resource for loan rates
    public function createRate(LoanRateType $loanRateType): View
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        return view('admin.loan-rate-types.rates.create', compact('loanRateType'));
    }

    public function downloadRatesTemplate(LoanRateType $loanRateType)
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        $headings = $this->rateRows->importTemplateHeadings($loanRateType);
        $sampleRows = collect($this->rateRows->importTemplateSampleRows($loanRateType));
        $instructionRows = collect($this->rateRows->importInstructionsRows($loanRateType));

        $filename = 'loan-rates-template-'.$loanRateType->code.'-'.now()->format('Y-m-d').'.xlsx';

        $sheets = [
            [
                'title' => 'Rates',
                'headings' => $headings,
                'rows' => $sampleRows,
                'columnWidths' => [
                    'A' => 16, 'B' => 26, 'C' => 24, 'D' => 14, 'E' => 14,
                    'F' => 16, 'G' => 16, 'H' => 14, 'I' => 12,
                ],
            ],
            [
                'title' => 'Instructions',
                'headings' => [],
                'rows' => $instructionRows,
                'columnWidths' => ['A' => 72, 'B' => 48],
            ],
        ];

        return Excel::download(new class($sheets) implements WithMultipleSheets
        {
            public function __construct(private readonly array $sheets) {}

            public function sheets(): array
            {
                return array_map(function (array $sheet) {
                    return new class($sheet) implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles
                    {
                        public function __construct(private readonly array $sheet) {}

                        public function collection()
                        {
                            $rows = $this->sheet['rows'];
                            if ($rows instanceof \Illuminate\Support\Collection) {
                                return $rows;
                            }

                            return collect($rows);
                        }

                        public function headings(): array
                        {
                            return $this->sheet['headings'];
                        }

                        public function title(): string
                        {
                            return $this->sheet['title'];
                        }

                        public function columnWidths(): array
                        {
                            return $this->sheet['columnWidths'] ?? [];
                        }

                        public function styles(Worksheet $worksheet)
                        {
                            return $this->sheet['headings'] !== []
                                ? [1 => ['font' => ['bold' => true]]]
                                : [];
                        }
                    };
                }, $this->sheets);
            }
        }, $filename);
    }

    public function importRates(Request $request, LoanRateType $loanRateType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        $request->validate([
            'rates_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $worksheets = Excel::toArray([], $request->file('rates_file'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', 'Failed to read the uploaded file: '.$e->getMessage());
        }

        $rows = $worksheets[0] ?? [];
        if (count($rows) < 2) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', 'The file must include a header row and at least one data row.');
        }

        $headers = array_map(
            fn ($header) => $this->normalizeImportHeader((string) $header),
            array_values((array) array_shift($rows))
        );

        $requiredHeaders = ['tenure_months', 'processing_fee_percentage'];
        $mode = $this->rateRows->resolveRateInputMode($loanRateType);
        if ($mode === LoanRateType::RATE_INPUT_TERM_PERCENTAGE) {
            $requiredHeaders[] = 'term_interest_percentage';
        } elseif ($mode === LoanRateType::RATE_INPUT_DAILY_MULTIPLIER) {
            $requiredHeaders[] = 'daily_rate';
        } else {
            $requiredHeaders[] = 'weekly_rate';
        }

        $missingHeaders = array_values(array_diff($requiredHeaders, $headers));
        if ($missingHeaders !== []) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', 'Missing required column(s): '.implode(', ', $missingHeaders).'.');
        }

        $created = 0;
        $updated = 0;
        $seenBands = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowNumber = $index + 2;
                $mappedRow = $this->mapRowToHeaders($headers, $row);

                if ($this->isImportRowEmpty($mappedRow)) {
                    continue;
                }

                $normalized = [
                    'tenure_months' => $this->parseIntegerValue($mappedRow['tenure_months'] ?? null),
                    'processing_fee_percentage' => $this->parseNumericValue($mappedRow['processing_fee_percentage'] ?? null),
                    'term_interest_percentage' => $this->parseNumericValue($mappedRow['term_interest_percentage'] ?? null),
                    'daily_rate' => $this->parseNumericValue($mappedRow['daily_rate'] ?? null),
                    'weekly_rate' => $this->parseNumericValue($mappedRow['weekly_rate'] ?? null),
                    'min_principal' => $this->parseNumericValue($mappedRow['min_principal'] ?? null),
                    'max_principal' => $this->parseNumericValue($mappedRow['max_principal'] ?? null),
                    'arrear_rate' => $this->parseNumericValue($mappedRow['arrear_rate'] ?? null),
                    'is_active' => $this->parseBooleanValue($mappedRow['is_active'] ?? null),
                ];

                if ($normalized['is_active'] === null) {
                    throw new \RuntimeException("Row {$rowNumber}: invalid is_active value. Use 1/0, true/false, yes/no, or active/inactive.");
                }

                $validator = Validator::make($normalized, $this->rateRows->importRowRules($loanRateType));

                if ($validator->fails()) {
                    throw new \RuntimeException("Row {$rowNumber}: ".$validator->errors()->first());
                }

                if ($normalized['min_principal'] !== null
                    && $normalized['max_principal'] !== null
                    && $normalized['max_principal'] < $normalized['min_principal']) {
                    throw new \RuntimeException("Row {$rowNumber}: max_principal must be greater than or equal to min_principal.");
                }

                $bandKey = $this->rateRows->bandKey(
                    $normalized['tenure_months'],
                    $normalized['min_principal'],
                    $normalized['max_principal']
                );
                if (in_array($bandKey, $seenBands, true)) {
                    throw new \RuntimeException("Row {$rowNumber}: duplicate tenure and principal band in uploaded file.");
                }
                $seenBands[] = $bandKey;

                $payload = $this->rateRows->prepareImportRow($loanRateType, $normalized);

                $existingRate = $this->rateRows->findExistingRate($loanRateType, $payload);

                if (! $existingRate) {
                    $this->rateRows->assertUniqueBand(
                        $loanRateType,
                        array_merge($payload, ['tenure_months' => $normalized['tenure_months']])
                    );
                }

                if ($existingRate) {
                    if ($existingRate->trashed()) {
                        $existingRate->restore();
                    }
                    $existingRate->fill($payload)->save();
                    $updated++;
                } else {
                    LoanRate::create([
                        'loan_rate_type_id' => $loanRateType->id,
                        'tenure_months' => $normalized['tenure_months'],
                        ...$payload,
                    ]);
                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', 'Failed to import rates: '.$e->getMessage());
        }

        if ($created === 0 && $updated === 0) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', 'No importable rows were found in the uploaded file.');
        }

        return redirect()
            ->route('admin.loan-rate-types.show', $loanRateType)
            ->with('status', "Rates imported successfully. {$created} created, {$updated} updated.");
    }

    public function copyToProduct(Request $request, LoanRateType $loanRateType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        $validated = $request->validate([
            'target_loan_product_id' => ['required', 'integer', 'exists:loan_products,id', Rule::notIn([$loanRateType->loan_product_id])],
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'unique:loan_rate_types,code'],
            'description' => ['nullable', 'string'],
        ]);

        $targetProduct = LoanProduct::findOrFail($validated['target_loan_product_id']);
        $copiedRatesCount = 0;

        try {
            $newRateType = DB::transaction(function () use ($loanRateType, $targetProduct, $validated, &$copiedRatesCount) {
                $newRateType = LoanRateType::create([
                    'loan_product_id' => $targetProduct->id,
                    'name' => $validated['name'] ?? $loanRateType->name,
                    'code' => $validated['code'] ?? $this->generateUniqueCopyCode($loanRateType, $targetProduct),
                    'description' => array_key_exists('description', $validated) && $validated['description'] !== null
                        ? $validated['description']
                        : $loanRateType->description,
                    'accrual_period' => $loanRateType->accrual_period,
                    'interest_behavior' => $loanRateType->interest_behavior,
                    'rate_input_mode' => $loanRateType->rate_input_mode,
                    'is_active' => $loanRateType->is_active,
                ]);

                $sourceRates = $loanRateType->loanRates()->get();
                $copiedRatesCount = $sourceRates->count();

                foreach ($sourceRates as $sourceRate) {
                    LoanRate::create([
                        'loan_rate_type_id' => $newRateType->id,
                        'tenure_months' => $sourceRate->tenure_months,
                        'processing_fee_percentage' => $sourceRate->processing_fee_percentage,
                        'term_interest_percentage' => $sourceRate->term_interest_percentage,
                        'min_principal' => $sourceRate->min_principal,
                        'max_principal' => $sourceRate->max_principal,
                        'daily_rate' => $sourceRate->daily_rate,
                        'weekly_rate' => $sourceRate->weekly_rate,
                        'derived_daily_rate' => $sourceRate->derived_daily_rate,
                        'arrear_rate' => $sourceRate->arrear_rate,
                        'is_active' => $sourceRate->is_active,
                    ]);
                }

                return $newRateType;
            });
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->withInput()
                ->with('error', 'Failed to copy rates: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.loan-rate-types.show', $newRateType)
            ->with('status', "Copied {$copiedRatesCount} rate(s) to {$targetProduct->name}.");
    }

    public function storeRate(Request $request, LoanRateType $loanRateType): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);

        $validated = $request->validate($this->rateRows->rateRowRules($loanRateType));
        $payload = $this->rateRows->prepareRatePayload($loanRateType, $validated);

        try {
            $this->rateRows->assertUniqueBand($loanRateType, $payload);
            LoanRate::create(array_merge($payload, ['loan_rate_type_id' => $loanRateType->id]));

            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('status', 'Loan rate created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-rate-types.rates.create', $loanRateType)
                ->withInput()
                ->with('error', 'Failed to create loan rate: '.$e->getMessage());
        }
    }

    public function editRate(LoanRateType $loanRateType, LoanRate $loanRate): View
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);
        abort_unless($loanRate->loan_rate_type_id === $loanRateType->id, 404);

        return view('admin.loan-rate-types.rates.edit', compact('loanRateType', 'loanRate'));
    }

    public function updateRate(Request $request, LoanRateType $loanRateType, LoanRate $loanRate): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.update'), 403);
        abort_unless($loanRate->loan_rate_type_id === $loanRateType->id, 404);

        $validated = $request->validate($this->rateRows->rateRowRules($loanRateType, $loanRate));
        $payload = $this->rateRows->prepareRatePayload($loanRateType, $validated);

        try {
            $this->rateRows->assertUniqueBand($loanRateType, $payload, $loanRate->id);
            $loanRate->update($payload);

            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('status', 'Loan rate updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-rate-types.rates.edit', [$loanRateType, $loanRate])
                ->withInput()
                ->with('error', 'Failed to update loan rate: '.$e->getMessage());
        }
    }

    public function destroyRate(LoanRateType $loanRateType, LoanRate $loanRate): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan-rate-types.delete'), 403);
        abort_unless($loanRate->loan_rate_type_id === $loanRateType->id, 404);

        try {
            $this->safety->assertRateDeletable($loanRate);
            $loanRate->delete();

            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('status', 'Loan rate deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.loan-rate-types.show', $loanRateType)
                ->with('error', 'Failed to delete loan rate: '.$e->getMessage());
        }
    }

    private function normalizeImportHeader(string $header): string
    {
        $normalized = Str::of($header)
            ->lower()
            ->trim()
            ->replace(['%', '(', ')', '-', '/'], ' ')
            ->replaceMatches('/\s+/', '_')
            ->toString();

        $aliases = [
            'tenure' => 'tenure_months',
            'tenure_month' => 'tenure_months',
            'months' => 'tenure_months',
            'month' => 'tenure_months',
            'processing_fee' => 'processing_fee_percentage',
            'processing_fee_percent' => 'processing_fee_percentage',
            'processing_fee_percentage' => 'processing_fee_percentage',
            'daily_interest_rate' => 'daily_rate',
            'weekly_interest_rate' => 'weekly_rate',
            'arrears_rate' => 'arrear_rate',
            'term_interest' => 'term_interest_percentage',
            'term_interest_percent' => 'term_interest_percentage',
            'interest_percentage' => 'term_interest_percentage',
            'interest_rate' => 'term_interest_percentage',
            'term_rate' => 'term_interest_percentage',
            'min_amount' => 'min_principal',
            'max_amount' => 'max_principal',
            'minimum_principal' => 'min_principal',
            'maximum_principal' => 'max_principal',
            'status' => 'is_active',
            'active' => 'is_active',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int|string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRowToHeaders(array $headers, array $row): array
    {
        $values = array_values($row);
        $values = array_pad($values, count($headers), null);

        return array_combine($headers, array_slice($values, 0, count($headers)));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseNumericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = str_replace([',', '%', ' '], '', (string) $value);
        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    private function parseIntegerValue(mixed $value): ?int
    {
        $numeric = $this->parseNumericValue($value);
        if ($numeric === null) {
            return null;
        }

        $rounded = round($numeric);
        if (abs($numeric - $rounded) > 0.000001) {
            return null;
        }

        return (int) $rounded;
    }

    private function parseBooleanValue(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            $numeric = (float) $value;
            if (abs($numeric - 1.0) < 0.000001) {
                return true;
            }
            if (abs($numeric) < 0.000001) {
                return false;
            }

            return null;
        }

        $normalized = Str::lower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'active' => true,
            '0', 'false', 'no', 'n', 'inactive' => false,
            default => null,
        };
    }

    private function generateUniqueCopyCode(LoanRateType $loanRateType, LoanProduct $targetProduct): string
    {
        $baseCode = Str::upper(Str::slug($loanRateType->code.'_'.$targetProduct->code, '_'));
        if ($baseCode === '') {
            $baseCode = "RATE_TYPE_{$loanRateType->id}_{$targetProduct->id}";
        }

        $candidate = Str::limit($baseCode, 255, '');
        $counter = 1;

        while (LoanRateType::withTrashed()->where('code', $candidate)->exists()) {
            $suffix = "_{$counter}";
            $candidate = Str::limit($baseCode, 255 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $candidate;
    }
}
