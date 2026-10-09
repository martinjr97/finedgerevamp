<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loan_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('accrual_date');
            $table->decimal('principal_balance', 14, 2);
            $table->decimal('interest_amount', 14, 2);
            $table->decimal('cumulative_interest', 14, 2);
            $table->decimal('total_balance', 14, 2);
            $table->string('accrual_period', 20)->nullable();
            $table->decimal('rate_used', 10, 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_loan_id', 'accrual_date'], 'emp_loan_accrual_date_uniq');
            $table->index('accrual_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_accruals');
    }
};
