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
        Schema::table('general_settings', function (Blueprint $table) {
            $table->boolean('auto_adjust_loan_limit_by_credit_score')->default(false)->after('missed_payment_reminder_count')->comment('Automatically adjust maximum_loan_take based on credit score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn('auto_adjust_loan_limit_by_credit_score');
        });
    }
};
