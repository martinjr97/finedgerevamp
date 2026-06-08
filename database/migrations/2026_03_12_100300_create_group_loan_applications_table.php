<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_loan_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_product_id')->constrained('loan_products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_group_id')->constrained('customer_groups')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('group_name');
            $table->string('loan_name');
            $table->text('terms_and_conditions')->nullable();
            $table->enum('repayment_structure', ['weekly', 'monthly']);
            $table->date('start_date');
            $table->date('due_date');
            $table->decimal('processing_fee_percentage', 8, 4);
            $table->decimal('monthly_interest_rate', 8, 4);
            $table->decimal('arrears_rate', 8, 4);
            $table->decimal('total_principal_amount', 14, 2)->default(0);
            $table->decimal('total_processing_fee_amount', 14, 2)->default(0);
            $table->decimal('total_interest_amount', 14, 2)->default(0);
            $table->decimal('total_repayment_amount', 14, 2)->default(0);
            $table->decimal('total_disbursement_amount', 14, 2)->default(0);
            $table->enum('status', ['pending_approval', 'awaiting_disbursement', 'partially_disbursed', 'disbursed', 'rejected'])->default('pending_approval');
            $table->foreignId('approved_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('start_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_loan_applications');
    }
};
