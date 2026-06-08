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
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->string('subject')->nullable()->comment('Subject for email communications');
            $table->text('message')->comment('Message content');
            $table->enum('type', ['sms', 'email', 'both'])->default('email');
            $table->json('filters')->nullable()->comment('Applied filters (product_id, gender, province_id, age_group, has_active_loans)');
            $table->integer('recipients_count')->default(0)->comment('Total number of recipients');
            $table->integer('sent_count')->default(0)->comment('Successfully sent count');
            $table->integer('failed_count')->default(0)->comment('Failed to send count');
            $table->enum('status', ['pending', 'sending', 'completed', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable()->comment('When the communication was sent');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Admin who created this communication');
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('created_by')->references('id')->on('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->index('status');
            $table->index('type');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
