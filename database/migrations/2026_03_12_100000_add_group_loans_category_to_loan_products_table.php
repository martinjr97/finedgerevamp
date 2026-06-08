<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loan_products MODIFY COLUMN category ENUM('government','mou','character','collateral','marketeer','sme','group_loans') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE loan_products SET category = 'character' WHERE category = 'group_loans'");
        DB::statement("ALTER TABLE loan_products MODIFY COLUMN category ENUM('government','mou','character','collateral','marketeer','sme') NOT NULL");
    }
};
