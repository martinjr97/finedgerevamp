<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();

            $table->decimal('amount', 14, 2);
            $table->decimal('principal_amount', 14, 2)->default(0);
            $table->decimal('interest_amount', 14, 2)->default(0);
            $table->decimal('processing_fee_amount', 14, 2)->default(0);
            $table->decimal('arrears_interest_amount', 14, 2)->default(0);

            $table->date('effective_date');
            $table->timestamp('processed_at')->nullable();

            $table->string('payment_method')->nullable();
            $table->enum('repayment_source', ['manual', 'payroll', 'bank', 'cash', 'adjustment'])->default('manual');

            $table->string('reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();

            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('pending');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('employee_loan_id');
            $table->index('effective_date');
            $table->index('repayment_source');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_repayments');
    }
};
