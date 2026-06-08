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
        Schema::create('repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('repayment_number')->unique()->comment('Unique repayment reference number');
            $table->decimal('total_amount', 14, 2)->comment('Total repayment amount');
            $table->string('phone_number')->nullable()->comment('Phone number used for repayment');
            $table->string('external_reference')->nullable()->comment('Reference from external payment processor');
            $table->string('external_transaction_id')->nullable()->comment('Transaction ID from external payment processor');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('status_message')->nullable()->comment('Status message or error description');
            $table->timestamp('processed_at')->nullable()->comment('When the repayment was processed');
            $table->json('metadata')->nullable()->comment('Additional metadata from payment processor');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('customer_id');
            $table->index('repayment_number');
            $table->index('external_reference');
            $table->index('status');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
