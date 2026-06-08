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
        Schema::create('loan_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_rate_type_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('tenure_months'); // 1, 2, 3, etc.
            $table->decimal('processing_fee_percentage', 5, 2); // e.g., 10.00 for 10%
            $table->decimal('daily_rate', 8, 5)->nullable(); // e.g., 0.03 for daily rate
            $table->decimal('weekly_rate', 8, 5)->nullable(); // e.g., 0.21 for weekly rate (for marketeer)
            $table->decimal('arrear_rate', 8, 5); // e.g., 0.03 for arrear rate per installment
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure unique combination of rate type and tenure
            $table->unique(['loan_rate_type_id', 'tenure_months']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_rates');
    }
};
