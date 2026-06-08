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
        Schema::create('customer_login_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->string('phone')->index(); // Store phone even if customer is deleted
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('device_name')->nullable(); // e.g., iPhone 13, Samsung Galaxy
            $table->string('browser')->nullable(); // Chrome, Safari, Firefox
            $table->string('browser_version')->nullable();
            $table->string('os')->nullable(); // iOS, Android, Windows, macOS, Linux
            $table->string('os_version')->nullable();
            $table->string('location_country')->nullable();
            $table->string('location_region')->nullable();
            $table->string('location_city')->nullable();
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->string('failure_reason')->nullable(); // e.g., 'invalid_credentials', 'account_inactive'
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['customer_id', 'attempted_at']);
            $table->index(['phone', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_login_audits');
    }
};
