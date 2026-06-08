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
        Schema::create('repayment_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_payment_schedule_id')->constrained('loan_payment_schedules')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('reminder_type', ['1_week_before', '2_days_before', '1_day_before', 'missed_1', 'missed_2'])->comment('Type of reminder sent');
            $table->date('reminder_date')->comment('Date when reminder was sent');
            $table->date('due_date')->comment('Due date of the payment');
            $table->decimal('amount', 14, 2)->comment('Amount due');
            $table->enum('communication_type', ['email', 'sms', 'both'])->default('both');
            $table->foreignId('communication_id')->nullable()->constrained('communications')->nullOnDelete()->comment('Link to communication record');
            $table->timestamps();
            
            // Indexes
            $table->index('loan_payment_schedule_id');
            $table->index('customer_id');
            $table->index('reminder_date');
            $table->index(['loan_payment_schedule_id', 'reminder_type'], 'rpl_schedule_type_idx');
            $table->index(['customer_id', 'due_date'], 'rpl_customer_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayment_reminder_logs');
    }
};
