<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow multiple rate rows per tenure when amount bands differ.
     *
     * Uniqueness for (tenure + min + max) is enforced in application code because
     * MySQL treats NULLs distinctly in unique indexes.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $indexes = collect(DB::select('SHOW INDEX FROM loan_rates'))
                ->pluck('Key_name')
                ->unique();

            if (! $indexes->contains('loan_rates_loan_rate_type_id_index')) {
                Schema::table('loan_rates', function (Blueprint $table) {
                    $table->index('loan_rate_type_id', 'loan_rates_loan_rate_type_id_index');
                });
            }
        }

        Schema::table('loan_rates', function (Blueprint $table) {
            $table->dropUnique(['loan_rate_type_id', 'tenure_months']);
        });
    }

    public function down(): void
    {
        Schema::table('loan_rates', function (Blueprint $table) {
            $table->unique(['loan_rate_type_id', 'tenure_months']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('loan_rates', function (Blueprint $table) {
                $table->dropIndex('loan_rates_loan_rate_type_id_index');
            });
        }
    }
};
