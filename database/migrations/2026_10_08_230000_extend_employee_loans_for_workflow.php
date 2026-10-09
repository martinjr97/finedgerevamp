<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            $table->foreignId('cancelled_by')->nullable()->after('rejected_by')->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');

            $table->timestamp('submitted_at')->nullable()->after('application_date');

            $table->string('disbursed_via_type', 32)->nullable()->after('disbursed_at');
            $table->unsignedBigInteger('disbursed_via_id')->nullable()->after('disbursed_via_type');
            $table->json('disbursement_destination_snapshot')->nullable()->after('disbursed_via_id');

            $table->decimal('settlement_amount', 14, 2)->nullable()->after('outstanding_balance');
            $table->date('loan_settled_date')->nullable()->after('last_payment_date');
            $table->date('settlement_date')->nullable()->after('loan_settled_date');

            $table->string('performance_status', 32)->nullable()->after('status');
            $table->timestamp('npl_at')->nullable()->after('performance_status');

            $table->index('performance_status');
            $table->index('loan_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
                'submitted_at',
                'disbursed_via_type',
                'disbursed_via_id',
                'disbursement_destination_snapshot',
                'settlement_amount',
                'loan_settled_date',
                'settlement_date',
                'performance_status',
                'npl_at',
            ]);
        });
    }
};
