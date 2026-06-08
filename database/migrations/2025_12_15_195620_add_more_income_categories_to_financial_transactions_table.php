<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include new income categories
        DB::statement("ALTER TABLE financial_transactions MODIFY COLUMN category ENUM(
            'loan_interest', 
            'loan_processing_fee', 
            'shareholder_contribution',
            'investment_income',
            'donation',
            'grant',
            'other_income',
            'operational', 
            'administrative', 
            'marketing', 
            'salaries', 
            'utilities', 
            'rent', 
            'other_expense',
            'transfer'
        ) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE financial_transactions MODIFY COLUMN category ENUM(
            'loan_interest', 
            'loan_processing_fee', 
            'other_income',
            'operational', 
            'administrative', 
            'marketing', 
            'salaries', 
            'utilities', 
            'rent', 
            'other_expense',
            'transfer'
        ) NULL");
    }
};
