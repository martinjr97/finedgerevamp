<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();

            $table->foreignId('employee_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('loan_rate_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();

            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'rejected',
                'active',
                'settled',
                'cancelled',
                'written_off',
            ])->default('draft');

            $table->enum('disbursement_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');

            $table->decimal('principal_amount', 14, 2)->default(0);
            $table->decimal('processing_fee', 14, 2)->default(0);
            $table->decimal('processing_fee_percentage', 5, 2)->nullable();
            $table->decimal('daily_rate', 10, 8)->nullable();
            $table->decimal('weekly_rate', 10, 8)->nullable();
            $table->decimal('quoted_term_rate', 8, 4)->nullable();
            $table->decimal('arrear_rate', 10, 5)->nullable();

            $table->string('interest_behavior', 32)->nullable();
            $table->enum('accrual_type', ['daily', 'at_beginning'])->default('at_beginning');
            $table->string('accrual_period', 20)->nullable();

            $table->unsignedTinyInteger('tenure_months')->default(1);
            $table->enum('repayment_frequency', ['monthly', 'weekly'])->default('monthly');

            $table->decimal('interest_accrued', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('outstanding_balance', 14, 2)->default(0);

            $table->date('application_date')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('loan_start_date')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->date('loan_end_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->date('last_accrual_date')->nullable();
            $table->date('arrears_last_accrual_date')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();

            $table->string('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('status');
            $table->index('disbursement_status');
            $table->index(['status', 'disbursement_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};
