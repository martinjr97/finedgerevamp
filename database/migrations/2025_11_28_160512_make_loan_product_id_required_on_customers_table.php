<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\LoanProduct;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any customers with NULL loan_product_id to the first available loan product
        $firstLoanProduct = LoanProduct::first();
        if ($firstLoanProduct) {
            DB::table('customers')
                ->whereNull('loan_product_id')
                ->update(['loan_product_id' => $firstLoanProduct->id]);
        }

        // Drop the existing foreign key constraint
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['loan_product_id']);
        });

        // Modify the column to be NOT NULL using raw SQL
        DB::statement('ALTER TABLE customers MODIFY loan_product_id BIGINT UNSIGNED NOT NULL');

        // Re-add the foreign key constraint without nullOnDelete
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('loan_product_id')
                ->references('id')
                ->on('loan_products')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // Prevent deletion of loan product if customers exist
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['loan_product_id']);
        });

        // Make the column nullable again using raw SQL
        DB::statement('ALTER TABLE customers MODIFY loan_product_id BIGINT UNSIGNED NULL');

        // Re-add the foreign key constraint with nullOnDelete
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('loan_product_id')
                ->references('id')
                ->on('loan_products')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }
};
