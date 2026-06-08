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
            $table->unsignedTinyInteger('loan_cut_off_day')->nullable()->after('maximum_debit_ratio')->comment('Day of month after which loan payment moves to next month (e.g., 25)');
            $table->unsignedTinyInteger('loan_payment_date')->nullable()->after('loan_cut_off_day')->comment('Day of month when loan payments are due (e.g., 30)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn(['loan_cut_off_day', 'loan_payment_date']);
        });
    }
};
