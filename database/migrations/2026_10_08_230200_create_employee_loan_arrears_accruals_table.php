<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loan_arrears_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_loan_payment_schedule_id')->nullable();
            $table->foreign('employee_loan_payment_schedule_id', 'emp_loan_arr_sched_fk')
                ->references('id')
                ->on('employee_loan_payment_schedules')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->date('accrual_date');
            $table->decimal('base_amount', 14, 2);
            $table->decimal('interest_amount', 14, 2);
            $table->decimal('rate_used', 10, 5)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_loan_id', 'employee_loan_payment_schedule_id', 'accrual_date'],
                'emp_loan_arr_accr_uniq'
            );
            $table->index('accrual_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_arrears_accruals');
    }
};
