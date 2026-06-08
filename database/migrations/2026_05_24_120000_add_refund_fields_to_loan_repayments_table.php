<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->string('transaction_type', 20)->default('payment')->after('loan_id');
            $table->foreignId('refund_of_loan_repayment_id')
                ->nullable()
                ->after('transaction_type')
                ->constrained('loan_repayments')
                ->nullOnDelete();

            $table->index('transaction_type');
            $table->index('refund_of_loan_repayment_id');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['refund_of_loan_repayment_id']);
            $table->dropIndex(['transaction_type']);
            $table->dropIndex(['refund_of_loan_repayment_id']);
            $table->dropColumn(['transaction_type', 'refund_of_loan_repayment_id']);
        });
    }
};
