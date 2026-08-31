<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Models\Bank;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\FinancialTransaction;
use App\Models\IncomeCategory;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialDataMigrator
{
    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $promote = false,
        ?string $only = null,
        ?string $fromDate = null,
        ?string $runUuid = null,
    ): array {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $fromBoundary = $this->resolveFromDate($fromDate);

        $run = $this->runManager->start('m2-financial', $only ?: 'full', $runUuid);
        $runId = $run['id'];

        $stats = [
            'run_uuid' => $run['run_uuid'],
            'migration_run_id' => $runId,
            'promote' => $promote,
            'from_date' => $fromBoundary->toDateString(),
            'expense_categories' => ['MATCHED' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0],
            'expense_subcategories' => ['MATCHED' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0],
            'income_categories' => ['MATCHED' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0],
            'expenses' => ['MATCHED' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0, 'SKIPPED' => 0, 'NO_SOURCE' => 0],
            'incomes' => ['MATCHED' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0, 'SKIPPED' => 0, 'NO_DESTINATION' => 0],
        ];

        if (! $only || in_array($only, ['categories', 'expense_categories'], true)) {
            $this->migrateExpenseCategories($legacy, $runId, $promote, $stats);
        }

        if (! $only || in_array($only, ['categories', 'income_categories'], true)) {
            $this->migrateIncomeCategories($legacy, $runId, $promote, $stats);
        }

        if (! $only || $only === 'expenses') {
            $this->migrateExpenses($legacy, $runId, $promote, $fromBoundary, $stats);
        }

        if (! $only || $only === 'incomes') {
            $this->migrateIncomes($legacy, $runId, $promote, $fromBoundary, $stats);
        }

        $this->runManager->complete($runId, $stats);

        return $stats;
    }

    private function resolveFromDate(?string $fromDate): Carbon
    {
        $configured = $fromDate ?: config('migration.financial_from_date') ?: env('MIGRATION_FINANCIAL_FROM_DATE');

        if ($configured) {
            return Carbon::parse($configured)->startOfDay();
        }

        return Carbon::today()->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateExpenseCategories($legacy, int $runId, bool $promote, array &$stats): void
    {
        foreach ($legacy->table('expense_categories')->orderBy('id')->get() as $row) {
            $legacyId = (int) $row->id;
            $legacyIdentifier = (string) $legacyId;
            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_EXPENSE_CATEGORY, $legacyIdentifier);
            if ($existingMap) {
                $stats['expense_categories']['MATCHED']++;

                continue;
            }

            $code = ExpenseCategory::generateUniqueCode((string) $row->name);
            $existing = ExpenseCategory::query()
                ->where('legacy_id', $legacyId)
                ->orWhere('name', (string) $row->name)
                ->first();

            if ($existing) {
                if ($promote && ! $existing->legacy_id) {
                    $existing->update(['legacy_id' => $legacyId]);
                }
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_EXPENSE_CATEGORY,
                    $legacyIdentifier,
                    ExpenseCategory::class,
                    $existing->id,
                    'matched_existing',
                    'HIGH',
                    null,
                    $runId,
                );
                $stats['expense_categories']['MATCHED']++;

                continue;
            }

            if (! $promote) {
                $stats['expense_categories']['WOULD_CREATE']++;

                continue;
            }

            $category = ExpenseCategory::create([
                'legacy_id' => $legacyId,
                'name' => (string) $row->name,
                'code' => $code,
                'description' => $row->description ?? null,
                'is_active' => (bool) ($row->is_active ?? true),
                'sort_order' => $legacyId,
            ]);

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_EXPENSE_CATEGORY,
                $legacyIdentifier,
                ExpenseCategory::class,
                $category->id,
                'created',
                'HIGH',
                null,
                $runId,
            );
            $this->maps->trackCreated($runId, 'expense_category', $category->id);
            $stats['expense_categories']['CREATED']++;
        }

        foreach ($legacy->table('expense_subcategories')->orderBy('id')->get() as $row) {
            $legacyId = (int) $row->id;
            $legacyIdentifier = (string) $legacyId;
            if ($this->maps->find(MigrationEntityMapRepository::TYPE_EXPENSE_SUBCATEGORY, $legacyIdentifier)) {
                $stats['expense_subcategories']['MATCHED']++;

                continue;
            }

            $categoryTargetId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_EXPENSE_CATEGORY,
                (string) ($row->expense_category_id ?? ''),
            );

            if (! $categoryTargetId) {
                continue;
            }

            $existing = ExpenseSubcategory::query()->where('legacy_id', $legacyId)->first()
                ?? ExpenseSubcategory::query()
                    ->where('expense_category_id', $categoryTargetId)
                    ->where('name', (string) $row->name)
                    ->first();

            if ($existing) {
                if ($promote && ! $existing->legacy_id) {
                    $existing->update([
                        'legacy_id' => $legacyId,
                        'code' => $row->code ?? $existing->code,
                        'description' => $row->description ?? $existing->description,
                        'is_active' => true,
                    ]);
                }

                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_EXPENSE_SUBCATEGORY,
                    $legacyIdentifier,
                    ExpenseSubcategory::class,
                    $existing->id,
                    'matched_existing',
                    'HIGH',
                    null,
                    $runId,
                );
                $stats['expense_subcategories']['MATCHED']++;

                continue;
            }

            if (! $promote) {
                $stats['expense_subcategories']['WOULD_CREATE']++;

                continue;
            }

            $subcategory = ExpenseSubcategory::create([
                'legacy_id' => $legacyId,
                'expense_category_id' => $categoryTargetId,
                'code' => $row->code ?? null,
                'name' => (string) $row->name,
                'description' => $row->description ?? null,
                'is_active' => true,
            ]);

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_EXPENSE_SUBCATEGORY,
                $legacyIdentifier,
                ExpenseSubcategory::class,
                $subcategory->id,
                'created',
                'HIGH',
                null,
                $runId,
            );
            $this->maps->trackCreated($runId, 'expense_subcategory', $subcategory->id);
            $stats['expense_subcategories']['CREATED']++;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateIncomeCategories($legacy, int $runId, bool $promote, array &$stats): void
    {
        foreach ($legacy->table('income_sources')->orderBy('id')->get() as $row) {
            $legacyId = (int) $row->id;
            $legacyIdentifier = (string) $legacyId;
            if ($this->maps->find(MigrationEntityMapRepository::TYPE_INCOME_CATEGORY, $legacyIdentifier)) {
                $stats['income_categories']['MATCHED']++;

                continue;
            }

            $code = strtoupper((string) ($row->code ?: IncomeCategory::generateUniqueCode((string) $row->name)));
            $existing = IncomeCategory::query()
                ->where('legacy_id', $legacyId)
                ->orWhere('code', $code)
                ->orWhere('name', (string) $row->name)
                ->first();

            if ($existing) {
                if ($promote && ! $existing->legacy_id) {
                    $existing->update(['legacy_id' => $legacyId]);
                }
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_INCOME_CATEGORY,
                    $legacyIdentifier,
                    IncomeCategory::class,
                    $existing->id,
                    'matched_existing',
                    'HIGH',
                    null,
                    $runId,
                );
                $stats['income_categories']['MATCHED']++;

                continue;
            }

            if (! $promote) {
                $stats['income_categories']['WOULD_CREATE']++;

                continue;
            }

            $category = IncomeCategory::create([
                'legacy_id' => $legacyId,
                'name' => (string) $row->name,
                'code' => $code,
                'description' => $row->description ?? null,
                'is_active' => (bool) ($row->is_active ?? true),
                'sort_order' => $legacyId,
                'is_system' => false,
            ]);

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_INCOME_CATEGORY,
                $legacyIdentifier,
                IncomeCategory::class,
                $category->id,
                'created',
                'HIGH',
                null,
                $runId,
            );
            $this->maps->trackCreated($runId, 'income_category', $category->id);
            $stats['income_categories']['CREATED']++;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateExpenses($legacy, int $runId, bool $promote, Carbon $fromBoundary, array &$stats): void
    {
        $rows = $legacy->table('expenses')
            ->whereDate('expense_date', '>=', $fromBoundary->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (string) $row->id;
            if ($this->maps->find(MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION, $legacyId, 'expense')) {
                $stats['expenses']['MATCHED']++;

                continue;
            }

            $categoryId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_EXPENSE_CATEGORY,
                (string) ($row->expense_category_id ?? ''),
            );
            $category = $categoryId ? ExpenseCategory::find($categoryId) : null;
            if (! $category) {
                $stats['expenses']['SKIPPED']++;

                continue;
            }

            $subcategoryId = null;
            if (! empty($row->expense_subcategory_id)) {
                $subcategoryId = $this->maps->targetId(
                    MigrationEntityMapRepository::TYPE_EXPENSE_SUBCATEGORY,
                    (string) $row->expense_subcategory_id,
                );
            }

            [$sourceType, $sourceId] = $this->resolveLegacyPaymentSource($row);
            if (! $sourceType || ! $sourceId) {
                $stats['expenses']['NO_SOURCE']++;

                continue;
            }

            if (! $promote) {
                $stats['expenses']['WOULD_CREATE']++;

                continue;
            }

            $description = trim((string) ($row->description ?: 'Legacy expense #'.$legacyId));
            [$importDescription, $importNotes] = $this->prepareImportedText(
                $description,
                'Imported from legacy expense #'.$legacyId,
            );

            $transaction = FinancialTransaction::create([
                'transaction_number' => $this->legacyTransactionNumber('EXP', $legacyId),
                'transaction_date' => $row->expense_date,
                'type' => 'expense',
                'category' => $category->code,
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $subcategoryId,
                'description' => $importDescription,
                'receiver_name' => filled($row->receiver_name ?? null) ? (string) $row->receiver_name : null,
                'amount' => $row->amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reference_number' => $row->reference_number ?? null,
                'notes' => $importNotes,
                'metadata' => [
                    'legacy_import' => true,
                    'legacy_table' => 'expenses',
                    'legacy_id' => (int) $legacyId,
                    'legacy_payment_method' => $row->payment_method ?? null,
                ],
                'created_by' => null,
                'approval_status' => 'approved',
            ]);

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION,
                $legacyId,
                FinancialTransaction::class,
                $transaction->id,
                'legacy_expense_import',
                'HIGH',
                'expense',
                $runId,
            );
            $this->maps->trackCreated($runId, 'financial_transaction', $transaction->id);
            $stats['expenses']['CREATED']++;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateIncomes($legacy, int $runId, bool $promote, Carbon $fromBoundary, array &$stats): void
    {
        $rows = $legacy->table('incomes')
            ->whereDate('income_date', '>=', $fromBoundary->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            if (! empty($row->repayment_id)) {
                $stats['incomes']['SKIPPED']++;

                continue;
            }

            $legacyId = (string) $row->id;
            if ($this->maps->find(MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION, $legacyId, 'income')) {
                $stats['incomes']['MATCHED']++;

                continue;
            }

            $categoryId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_INCOME_CATEGORY,
                (string) ($row->income_source_id ?? ''),
            );
            $category = $categoryId ? IncomeCategory::find($categoryId) : null;
            if (! $category) {
                $stats['incomes']['SKIPPED']++;

                continue;
            }

            [$destinationType, $destinationId] = $this->resolveLegacyPaymentDestination($row);
            if (! $destinationType || ! $destinationId) {
                $stats['incomes']['NO_DESTINATION']++;

                continue;
            }

            if (! $promote) {
                $stats['incomes']['WOULD_CREATE']++;

                continue;
            }

            $description = trim((string) ($row->description ?: 'Legacy income #'.$legacyId));
            [$importDescription, $importNotes] = $this->prepareImportedText(
                $description,
                'Imported from legacy income #'.$legacyId,
            );

            $transaction = FinancialTransaction::create([
                'transaction_number' => $this->legacyTransactionNumber('INC', $legacyId),
                'transaction_date' => $row->income_date,
                'type' => 'income',
                'category' => $category->code,
                'income_category_id' => $category->id,
                'description' => $importDescription,
                'amount' => $row->amount,
                'destination_type' => $destinationType,
                'destination_id' => $destinationId,
                'reference_number' => $row->reference_number ?? null,
                'notes' => $importNotes,
                'metadata' => [
                    'legacy_import' => true,
                    'legacy_table' => 'incomes',
                    'legacy_id' => (int) $legacyId,
                    'legacy_payment_channel' => $row->payment_channel ?? null,
                ],
                'created_by' => null,
                'approval_status' => 'approved',
            ]);

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION,
                $legacyId,
                FinancialTransaction::class,
                $transaction->id,
                'legacy_income_import',
                'HIGH',
                'income',
                $runId,
            );
            $this->maps->trackCreated($runId, 'financial_transaction', $transaction->id);
            $stats['incomes']['CREATED']++;
        }
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveLegacyPaymentSource(object $row): array
    {
        if (! empty($row->bank_id)) {
            $bankId = $this->maps->targetId(MigrationEntityMapRepository::TYPE_BANK, (string) $row->bank_id);
            if ($bankId && Bank::query()->whereKey($bankId)->exists()) {
                return ['bank', $bankId];
            }
        }

        if (! empty($row->wallet_id)) {
            $wallet = $this->resolveLegacyTreasuryWallet((int) $row->wallet_id);
            if ($wallet) {
                return ['wallet', $wallet->id];
            }
        }

        return [null, null];
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveLegacyPaymentDestination(object $row): array
    {
        return $this->resolveLegacyPaymentSource($row);
    }

    private function resolveLegacyTreasuryWallet(int $legacyWalletId): ?Wallet
    {
        $mappedId = $this->maps->targetId(MigrationEntityMapRepository::TYPE_TREASURY_WALLET, (string) $legacyWalletId);
        if ($mappedId) {
            return Wallet::query()->whereKey($mappedId)->first();
        }

        $legacy = LegacyConnection::connection();
        $legacyWallet = $legacy->table('payment_wallets')->where('id', $legacyWalletId)->first();
        if (! $legacyWallet) {
            return null;
        }

        $wallet = Wallet::query()
            ->where('name', (string) ($legacyWallet->name ?? ''))
            ->orWhere('wallet_number', (string) ($legacyWallet->account_number ?? $legacyWallet->wallet_number ?? ''))
            ->first();

        if ($wallet) {
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_TREASURY_WALLET,
                (string) $legacyWalletId,
                Wallet::class,
                $wallet->id,
                'matched_by_name',
                'MEDIUM',
            );
        }

        return $wallet;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function prepareImportedText(string $description, string $notesPrefix): array
    {
        $maxLength = 500;

        if (Str::length($description) <= $maxLength) {
            return [$description, $notesPrefix];
        }

        return [
            Str::substr($description, 0, $maxLength),
            $notesPrefix."\n\n".$description,
        ];
    }

    private function legacyTransactionNumber(string $prefix, string $legacyId): string
    {
        return $prefix.'-LEG-'.str_pad($legacyId, 6, '0', STR_PAD_LEFT);
    }
}
