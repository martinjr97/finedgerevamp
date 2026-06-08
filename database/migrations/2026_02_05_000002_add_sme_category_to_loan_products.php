<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend enum to include 'sme'
        DB::statement("ALTER TABLE loan_products MODIFY COLUMN category ENUM('government','mou','character','collateral','marketeer','sme') NOT NULL");
    }

    public function down(): void
    {
        // Revert to previous enum (this will fail if rows use 'sme')
        DB::statement("ALTER TABLE loan_products MODIFY COLUMN category ENUM('government','mou','character','collateral','marketeer') NOT NULL");
    }
};
