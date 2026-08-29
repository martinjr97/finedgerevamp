<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->foreignId('expense_subcategory_id')->nullable()->after('expense_category_id')->constrained()->nullOnDelete();
            $table->foreignId('income_category_id')->nullable()->after('expense_subcategory_id')->constrained()->nullOnDelete();
        });

        DB::statement('ALTER TABLE financial_transactions MODIFY category VARCHAR(100) NULL');
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('income_category_id');
            $table->dropConstrainedForeignId('expense_subcategory_id');
            $table->dropConstrainedForeignId('expense_category_id');
        });
    }
};
