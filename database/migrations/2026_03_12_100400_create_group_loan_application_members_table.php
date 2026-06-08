<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_loan_application_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_loan_application_id')->constrained('group_loan_applications')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('group_member_title_id')->nullable()->constrained('group_member_titles')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('calculated_processing_fee_amount', 14, 2)->default(0);
            $table->decimal('calculated_interest_amount', 14, 2)->default(0);
            $table->decimal('calculated_arrears_basis_amount', 14, 2)->default(0);
            $table->decimal('calculated_total_repayment_amount', 14, 2)->default(0);
            $table->decimal('disbursement_amount', 14, 2)->default(0);
            $table->foreignId('loan_id')->nullable()->constrained('loans')->cascadeOnUpdate()->nullOnDelete();
            $table->string('disbursement_account_reference')->nullable();
            $table->enum('disbursement_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_reference')->nullable();
            $table->text('disbursement_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['group_loan_application_id', 'customer_id'], 'group_loan_member_unique');
            $table->index(['group_loan_application_id', 'disbursement_status'], 'group_loan_member_disbursement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_loan_application_members');
    }
};
