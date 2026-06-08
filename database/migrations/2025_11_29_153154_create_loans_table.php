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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('loan_rate_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('loan_number')->unique();
            
            // Loan amounts
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('processing_fee', 14, 2)->default(0);
            $table->decimal('processing_fee_percentage', 5, 2)->nullable()->comment('Stored processing fee percentage at loan creation');
            $table->decimal('daily_rate', 10, 8)->nullable()->comment('Stored daily rate at loan creation');
            $table->decimal('weekly_rate', 10, 8)->nullable()->comment('Stored weekly rate at loan creation');
            $table->string('accrual_period', 20)->nullable()->comment('daily or weekly - stored at loan creation');
            $table->decimal('interest_accrued', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('outstanding_balance', 14, 2);
            
            // Loan terms
            $table->unsignedTinyInteger('tenure_months');
            $table->date('loan_start_date');
            $table->date('loan_end_date');
            $table->date('first_payment_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->date('loan_settled_date')->nullable();
            
            // Accrual information
            $table->enum('accrual_type', ['daily', 'at_beginning']);
            $table->date('last_accrual_date')->nullable();
            
            // Status and approval
            $table->enum('status', ['pending_approval', 'approved', 'active', 'completed', 'defaulted', 'cancelled'])->default('pending_approval');
            $table->foreignId('approved_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Disbursement
            $table->string('disbursement_phone_number')->nullable();
            $table->enum('disbursement_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('disbursed_at')->nullable();
            $table->text('disbursement_notes')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('loan_number');
            $table->index('status');
            $table->index('loan_start_date');
            $table->index('loan_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
