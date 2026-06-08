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
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repayment_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('amount', 14, 2)->comment('Total amount applied to this loan from the repayment');
            $table->decimal('principal_amount', 14, 2)->default(0)->comment('Portion of amount applied to principal');
            $table->decimal('interest_amount', 14, 2)->default(0)->comment('Portion of amount applied to interest');
            $table->decimal('processing_fee_amount', 14, 2)->default(0)->comment('Portion of amount applied to processing fee');
            $table->decimal('outstanding_balance_before', 14, 2)->comment('Outstanding balance before this repayment');
            $table->decimal('outstanding_balance_after', 14, 2)->comment('Outstanding balance after this repayment');
            $table->text('notes')->nullable()->comment('Additional notes about this repayment allocation');
            $table->timestamps();
            
            // Indexes
            $table->index('repayment_id');
            $table->index('loan_id');
            $table->unique(['repayment_id', 'loan_id'], 'repayment_loan_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
