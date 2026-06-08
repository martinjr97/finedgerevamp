<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->decimal('instalment_cross_over_percentage', 5, 2)->nullable()->after('max_loan_tenure_months');
            $table->decimal('maximum_debit_ratio', 5, 2)->nullable()->after('instalment_cross_over_percentage');
            // Note: max_loan_tenure_months already exists, so we're not adding maximum_loan_tenure_months
            // If you want to rename it, we can do that in a separate migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn(['instalment_cross_over_percentage', 'maximum_debit_ratio']);
        });
    }
};
