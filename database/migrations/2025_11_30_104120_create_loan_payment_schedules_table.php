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
        Schema::create('loan_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number')->comment('Payment period number (1, 2, 3, etc.)');
            $table->date('due_date')->comment('Due date for this payment period');
            $table->decimal('expected_amount', 14, 2)->comment('Expected payment amount for this period');
            $table->decimal('amount_paid', 14, 2)->default(0)->comment('Amount paid against this period');
            $table->decimal('remaining_amount', 14, 2)->comment('Remaining amount due for this period');
            $table->enum('status', ['upcoming', 'paid', 'partial', 'overdue', 'paid_early'])->default('upcoming');
            $table->date('paid_at')->nullable()->comment('Date when this period was fully paid');
            $table->integer('days_overdue')->default(0)->comment('Number of days overdue if status is overdue');
            $table->timestamps();
            
            // Indexes
            $table->index('loan_id');
            $table->index('due_date');
            $table->index('status');
            $table->index(['loan_id', 'period_number']);
            $table->index(['due_date', 'status']);
            
            // Ensure period number is unique per loan
            $table->unique(['loan_id', 'period_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payment_schedules');
    }
};
