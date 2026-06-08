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
        Schema::create('collateral_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category')->comment('Type of collateral, e.g., Vehicle, Property, Equipment, etc.');
            $table->text('description')->nullable();
            $table->decimal('min_value', 14, 2)->nullable()->comment('Minimum acceptable value');
            $table->decimal('max_value', 14, 2)->nullable()->comment('Maximum acceptable value');
            $table->decimal('loan_to_value_ratio', 5, 2)->nullable()->comment('Loan to value ratio percentage (e.g., 70 means 70% of collateral value)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('loan_product_id');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_types');
    }
};
