<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_payment_details')) {
            return;
        }

        Schema::table('customer_payment_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_payment_details', 'method_type')) {
                $table->string('method_type', 32)->default('bank')->after('customer_id');
            }

            if (! Schema::hasColumn('customer_payment_details', 'wallet_provider')) {
                $table->string('wallet_provider')->nullable()->after('account_number');
            }

            if (! Schema::hasColumn('customer_payment_details', 'wallet_number')) {
                $table->string('wallet_number', 20)->nullable()->after('wallet_provider');
            }
        });

        $nullableColumns = [
            ['bank_name', 'VARCHAR(255)'],
            ['bank_branch', 'VARCHAR(255)'],
            ['account_name', 'VARCHAR(255)'],
            ['account_number', 'VARCHAR(50)'],
        ];

        foreach ($nullableColumns as [$column, $type]) {
            if (! Schema::hasColumn('customer_payment_details', $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `customer_payment_details` MODIFY `{$column}` {$type} NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_payment_details')) {
            return;
        }

        Schema::table('customer_payment_details', function (Blueprint $table): void {
            foreach (['wallet_number', 'wallet_provider', 'method_type'] as $column) {
                if (Schema::hasColumn('customer_payment_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        $requiredColumns = [
            ['bank_name', 'VARCHAR(255)'],
            ['bank_branch', 'VARCHAR(255)'],
            ['account_name', 'VARCHAR(255)'],
            ['account_number', 'VARCHAR(50)'],
        ];

        foreach ($requiredColumns as [$column, $type]) {
            if (! Schema::hasColumn('customer_payment_details', $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `customer_payment_details` MODIFY `{$column}` {$type} NOT NULL");
        }
    }
};

