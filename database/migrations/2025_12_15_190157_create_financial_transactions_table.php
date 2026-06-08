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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->date('transaction_date');
            $table->enum('type', ['income', 'expense', 'transfer']); // transfer is for bank/wallet transfers
            $table->enum('category', [
                // Income categories
                'loan_interest', 'loan_processing_fee', 'other_income',
                // Expense categories
                'operational', 'administrative', 'marketing', 'salaries', 'utilities', 'rent', 'other_expense',
                // Transfer (no category needed)
                'transfer'
            ])->nullable();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            
            // Source (for transfers and expenses)
            $table->enum('source_type', ['bank', 'wallet'])->nullable();
            $table->unsignedBigInteger('source_id')->nullable(); // bank_id or wallet_id
            
            // Destination (for transfers and income)
            $table->enum('destination_type', ['bank', 'wallet'])->nullable();
            $table->unsignedBigInteger('destination_id')->nullable(); // bank_id or wallet_id
            
            // For transfers: source and destination are both set
            // For income: only destination is set
            // For expenses: only source is set
            
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // admin_id
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['source_type', 'source_id']);
            $table->index(['destination_type', 'destination_id']);
            $table->index('transaction_date');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
