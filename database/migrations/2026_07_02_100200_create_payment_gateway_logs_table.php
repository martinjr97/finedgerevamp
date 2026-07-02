<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->foreignId('payment_gateway_attempt_id')->nullable()->constrained('payment_gateway_attempts')->nullOnDelete();
            $table->string('direction')->nullable();
            $table->string('event');
            $table->string('level')->default('info');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('payment_gateway_id');
            $table->index('payment_gateway_attempt_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};
