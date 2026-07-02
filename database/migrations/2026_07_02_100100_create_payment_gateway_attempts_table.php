<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->string('direction'); // collection, disbursement
            $table->string('purpose'); // loan_repayment, loan_disbursement
            $table->string('attemptable_type');
            $table->unsignedBigInteger('attemptable_id');
            $table->string('internal_reference')->unique();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('provider_transaction_id')->nullable();
            $table->string('payment_method'); // mobile_money, bank, card, manual
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('ZMW');
            $table->string('source_account')->nullable();
            $table->string('destination_account')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('created');
            $table->integer('response_code')->nullable();
            $table->text('response_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->unsignedInteger('query_attempts')->default(0);
            $table->timestamp('next_query_at')->nullable();
            $table->timestamp('last_queried_at')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['attemptable_type', 'attemptable_id']);
            $table->index('payment_gateway_id');
            $table->index('status');
            $table->index('next_query_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_attempts');
    }
};
