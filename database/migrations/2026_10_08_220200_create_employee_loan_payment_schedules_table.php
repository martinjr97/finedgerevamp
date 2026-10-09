<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loan_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number');
            $table->date('due_date');

            $table->decimal('principal_component', 14, 2)->nullable();
            $table->decimal('interest_component', 14, 2)->nullable();
            $table->decimal('fee_component', 14, 2)->nullable();

            $table->decimal('expected_amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2);

            $table->enum('status', ['upcoming', 'paid', 'partial', 'overdue', 'paid_early'])->default('upcoming');
            $table->integer('days_overdue')->default(0);
            $table->date('paid_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['employee_loan_id', 'period_number'], 'emp_loan_sched_loan_period_uniq');
            $table->index('due_date');
            $table->index('status');
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_payment_schedules');
    }
};
