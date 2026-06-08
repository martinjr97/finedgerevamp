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
        Schema::create('loan_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('accrual_date');
            $table->decimal('principal_balance', 14, 2)->comment('Principal balance at time of accrual');
            $table->decimal('interest_amount', 14, 2)->comment('Interest accrued on this date');
            $table->decimal('cumulative_interest', 14, 2)->comment('Total interest accrued up to this date');
            $table->decimal('total_balance', 14, 2)->comment('Total balance (principal + cumulative interest)');
            $table->string('accrual_period', 20)->comment('daily or weekly');
            $table->decimal('rate_used', 10, 8)->comment('The rate used for this accrual (daily_rate or weekly_rate)');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('loan_id');
            $table->index('accrual_date');
            $table->unique(['loan_id', 'accrual_date'], 'loan_accrual_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_accruals');
    }
};
