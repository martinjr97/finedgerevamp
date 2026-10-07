<?php

namespace App\Migration\ParallelRun;

use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParallelRunExpenseImportService
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
        private readonly ParallelRunTreasuryResolver $treasury,
    ) {}

    /**
     * Import a legacy expense row into revamp and optionally update treasury balances.
     *
     * @param  array<string, mixed>  $legacyExpense
     * @return array{status: string, transaction_id?: int, message?: string}
     */
    public function importLegacyExpense(array $legacyExpense, bool $applyBalanceUpdate = true): array
    {
        $legacyId = (string) ($legacyExpense['id'] ?? '');
        if ($legacyId === '') {
            return ['status' => 'skipped', 'message' => 'Missing legacy expense id'];
        }

        $existingMap = $this->maps->find(
            MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION,
            $legacyId,
            'expense',
        );
        if ($existingMap) {
            $transaction = FinancialTransaction::query()->find((int) $existingMap->target_id);
            if ($transaction) {
                $this->ensureBalanceUpdated($transaction, $applyBalanceUpdate);
            }

            return ['status' => 'matched', 'transaction_id' => (int) $existingMap->target_id];
        }

        $transactionNumber = $this->legacyTransactionNumber($legacyId);
        $existingTransaction = FinancialTransaction::query()
            ->where('transaction_number', $transactionNumber)
            ->first();
        if ($existingTransaction) {
            $this->ensureBalanceUpdated($existingTransaction, $applyBalanceUpdate);

            return ['status' => 'matched', 'transaction_id' => $existingTransaction->id];
        }

        $categoryId = $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_EXPENSE_CATEGORY,
            (string) ($legacyExpense['expense_category_id'] ?? ''),
        );
        $category = $categoryId ? ExpenseCategory::find($categoryId) : null;
        if (! $category) {
            return ['status' => 'skipped', 'message' => 'Expense category not mapped'];
        }

        if ($this->shouldSkipCategory($category->name)) {
            return ['status' => 'skipped', 'message' => 'Category excluded from parallel-run expense sync'];
        }

        [$sourceType, $sourceId] = $this->treasury->resolvePaymentSource($legacyExpense);
        if (! $sourceType || ! $sourceId) {
            return ['status' => 'skipped', 'message' => 'Treasury source not mapped'];
        }

        $subcategoryId = null;
        if (! empty($legacyExpense['expense_subcategory_id'])) {
            $subcategoryId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_EXPENSE_SUBCATEGORY,
                (string) $legacyExpense['expense_subcategory_id'],
            );
        }

        $creditorId = null;
        if (! empty($legacyExpense['creditor_id'])) {
            $creditorId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_CREDITOR,
                (string) $legacyExpense['creditor_id'],
            );
        }

        $description = trim((string) ($legacyExpense['description'] ?: 'Legacy expense #'.$legacyId));
        [$importDescription, $importNotes] = $this->prepareImportedText(
            $description,
            'Imported from legacy expense #'.$legacyId.' (parallel run)',
        );

        return DB::transaction(function () use (
            $legacyExpense,
            $legacyId,
            $transactionNumber,
            $category,
            $subcategoryId,
            $creditorId,
            $sourceType,
            $sourceId,
            $importDescription,
            $importNotes,
            $applyBalanceUpdate,
        ) {
            $transaction = FinancialTransaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $legacyExpense['expense_date'] ?? now()->toDateString(),
                'type' => 'expense',
                'category' => $category->code,
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $subcategoryId,
                'description' => $importDescription,
                'receiver_name' => filled($legacyExpense['receiver_name'] ?? null) ? (string) $legacyExpense['receiver_name'] : null,
                'creditor_id' => $creditorId,
                'amount' => $legacyExpense['amount'],
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reference_number' => $legacyExpense['reference_number'] ?? null,
                'notes' => $importNotes,
                'metadata' => [
                    'legacy_import' => true,
                    'legacy_table' => 'expenses',
                    'legacy_id' => (int) $legacyId,
                    'legacy_payment_method' => $legacyExpense['payment_method'] ?? null,
                    'parallel_run' => true,
                    'balance_updated' => $applyBalanceUpdate,
                ],
                'created_by' => null,
                'approval_status' => 'approved',
            ]);

            if ($applyBalanceUpdate && config('legacy-parallel-run.finance_on_import_enabled', false)) {
                $transaction->updateBalances();
            }

            $this->maps->store(
                MigrationEntityMapRepository::TYPE_FINANCIAL_TRANSACTION,
                $legacyId,
                FinancialTransaction::class,
                $transaction->id,
                'parallel_run_expense_import',
                'HIGH',
                'expense',
            );

            return ['status' => 'created', 'transaction_id' => $transaction->id];
        });
    }

    public function importLoanDisbursementExpense(int $legacyLoanId, float $principalAmount): array
    {
        try {
            \App\Migration\LegacyConnection::configureFromLegacyEnvFile();
            $legacy = \App\Migration\LegacyConnection::connection();
            $expense = (array) $legacy->table('expenses')
                ->where('reference_number', 'LOAN-DISB-'.$legacyLoanId)
                ->first();
        } catch (\Throwable $e) {
            return ['status' => 'skipped', 'message' => 'Legacy DB unavailable: '.$e->getMessage()];
        }

        if ($expense !== []) {
            return $this->importLegacyExpense($expense, applyBalanceUpdate: true);
        }

        $wallet = $this->treasury->resolveDefaultDisbursementWallet();
        if (! $wallet || $principalAmount <= 0) {
            return ['status' => 'skipped', 'message' => 'No LOAN-DISB expense and no default wallet configured'];
        }

        return [
            'status' => 'fallback_wallet',
            'wallet_id' => $wallet->id,
            'message' => 'No legacy LOAN-DISB expense — debit applied to default wallet',
        ];
    }

    private function shouldSkipCategory(?string $categoryName): bool
    {
        if ($categoryName === null) {
            return false;
        }

        $normalized = Str::lower(trim($categoryName));

        return in_array($normalized, ['inhouse transfer', 'in-house transfer'], true);
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

    private function legacyTransactionNumber(string $legacyId): string
    {
        return 'EXP-LEG-'.str_pad($legacyId, 6, '0', STR_PAD_LEFT);
    }

    private function ensureBalanceUpdated(FinancialTransaction $transaction, bool $applyBalanceUpdate): void
    {
        if (! $applyBalanceUpdate || ! config('legacy-parallel-run.finance_on_import_enabled', false)) {
            return;
        }

        $metadata = $transaction->metadata ?? [];
        if (($metadata['balance_updated'] ?? false) === true) {
            return;
        }

        $transaction->updateBalances();
        $transaction->update([
            'metadata' => array_merge($metadata, [
                'balance_updated' => true,
                'parallel_run_balance_catch_up' => now()->toIso8601String(),
            ]),
        ]);
    }
}
