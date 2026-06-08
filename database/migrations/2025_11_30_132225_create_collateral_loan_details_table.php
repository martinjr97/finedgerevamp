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
        Schema::create('collateral_loan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('collateral_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('collateral_value', 14, 2)->comment('The value of the collateral as provided by the customer');
            $table->decimal('loan_to_value_amount', 14, 2)->comment('Maximum loan amount based on LTV ratio');
            $table->decimal('loan_to_value_ratio', 5, 2)->comment('LTV ratio used at application time');
            $table->text('collateral_description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('loan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_loan_details');
    }
};
