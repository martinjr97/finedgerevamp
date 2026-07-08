<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();

            $table->nullableMorphs('recipient');

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();

            $table->string('phone_number');
            $table->string('normalized_phone')->nullable();

            $table->string('message_category');
            $table->string('message_type');

            $table->text('message_body');
            $table->string('message_preview')->nullable();
            $table->unsignedSmallInteger('message_length')->nullable();

            $table->string('provider');
            $table->string('status');

            $table->string('skip_reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('provider_response')->nullable();

            $table->unsignedTinyInteger('attempt_count')->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['message_category', 'created_at']);
            $table->index(['phone_number', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
