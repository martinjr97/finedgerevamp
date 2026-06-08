<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_registration_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('national_id')->nullable();
            $table->string('tpin')->nullable();
            $table->string('status')->default('pending'); // pending, reverted, approved, rejected
            $table->json('payload')->nullable(); // stores full form data for later processing
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_registration_requests');
    }
};


