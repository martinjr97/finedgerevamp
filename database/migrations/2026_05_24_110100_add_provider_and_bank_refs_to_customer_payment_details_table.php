<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $column): bool
    {
        try {
            $rows = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1',
                [$table, $column]
            );

            return count($rows) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function constraintExists(string $table, string $constraintName): bool
    {
        try {
            $rows = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                 LIMIT 1',
                [$table, $constraintName]
            );

            return count($rows) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        if (! Schema::hasTable('customer_payment_details')) {
            return;
        }

        Schema::table('customer_payment_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_payment_details', 'bank_financial_institution_id')) {
                $table->foreignId('bank_financial_institution_id')
                    ->nullable()
                    ->after('method_type');
            }

            if (! Schema::hasColumn('customer_payment_details', 'bank_financial_institution_branch_id')) {
                $table->foreignId('bank_financial_institution_branch_id')
                    ->nullable()
                    ->after('bank_financial_institution_id');
            }

            if (! Schema::hasColumn('customer_payment_details', 'wallet_provider_id')) {
                $table->foreignId('wallet_provider_id')
                    ->nullable()
                    ->after('wallet_provider');
            }
        });

        if (
            Schema::hasColumn('customer_payment_details', 'bank_financial_institution_id')
            && ! $this->foreignKeyExists('customer_payment_details', 'bank_financial_institution_id')
        ) {
            Schema::table('customer_payment_details', function (Blueprint $table): void {
                $table->foreign('bank_financial_institution_id', 'cpd_bfi_fk')
                    ->references('id')
                    ->on('financial_institutions')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (
            Schema::hasColumn('customer_payment_details', 'bank_financial_institution_branch_id')
            && ! $this->foreignKeyExists('customer_payment_details', 'bank_financial_institution_branch_id')
        ) {
            Schema::table('customer_payment_details', function (Blueprint $table): void {
                $table->foreign('bank_financial_institution_branch_id', 'cpd_bfib_fk')
                    ->references('id')
                    ->on('financial_institution_branches')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (
            Schema::hasColumn('customer_payment_details', 'wallet_provider_id')
            && ! $this->foreignKeyExists('customer_payment_details', 'wallet_provider_id')
        ) {
            Schema::table('customer_payment_details', function (Blueprint $table): void {
                $table->foreign('wallet_provider_id', 'cpd_wpid_fk')
                    ->references('id')
                    ->on('wallet_providers')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_payment_details')) {
            return;
        }

        foreach ([
            'cpd_wpid_fk',
            'cpd_bfib_fk',
            'cpd_bfi_fk',
            // Fallbacks if the FKs were created with Laravel's default naming.
            'customer_payment_details_wallet_provider_id_foreign',
            'customer_payment_details_bank_financial_institution_id_foreign',
        ] as $constraint) {
            if ($this->constraintExists('customer_payment_details', $constraint)) {
                Schema::table('customer_payment_details', function (Blueprint $table) use ($constraint): void {
                    $table->dropForeign($constraint);
                });
            }
        }

        Schema::table('customer_payment_details', function (Blueprint $table): void {
            foreach ([
                'wallet_provider_id',
                'bank_financial_institution_branch_id',
                'bank_financial_institution_id',
            ] as $column) {
                if (Schema::hasColumn('customer_payment_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
