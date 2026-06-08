<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->default(1)->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('category', ['government', 'mou', 'character', 'collateral', 'marketeer']);
            $table->text('description')->nullable();
            $table->unsignedInteger('tenure_months')->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->boolean('requires_collateral')->default(false);
            $table->boolean('requires_reference')->default(false);
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
