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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tpin')->nullable()->after('registration_number');
            $table->date('mou_expiry_date')->nullable()->after('date_of_incorporation');
            $table->unsignedSmallInteger('maximum_loan_tenure_months')->nullable()->after('loan_rate_type_id');
            $table->unsignedTinyInteger('monthly_cut_off_day')->nullable()->after('maximum_loan_tenure_months');
            $table->unsignedTinyInteger('pay_day')->nullable()->after('monthly_cut_off_day');
            $table->decimal('maximum_debit_ratio', 5, 2)->default(40.00)->after('pay_day');
            $table->decimal('instalment_cross_over_percentage', 5, 2)->default(5.00)->after('maximum_debit_ratio');
            $table->decimal('arrangement_fee_percentage', 5, 2)->default(0.00)->after('instalment_cross_over_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'tpin',
                'mou_expiry_date',
                'maximum_loan_tenure_months',
                'monthly_cut_off_day',
                'pay_day',
                'maximum_debit_ratio',
                'instalment_cross_over_percentage',
                'arrangement_fee_percentage',
            ]);
        });
    }
};
