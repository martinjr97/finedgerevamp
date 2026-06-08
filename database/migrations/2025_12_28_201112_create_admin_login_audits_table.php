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
        Schema::create('admin_login_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('cascade');
            $table->string('email')->index(); // Store email even if admin is deleted
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->string('failure_reason')->nullable(); // e.g., 'invalid_credentials', 'account_inactive', 'account_pending'
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['admin_id', 'attempted_at']);
            $table->index(['email', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_login_audits');
    }
};
