<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_customer_inbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_user_id')->unique();
            $table->string('status')->default('pending_review');
            $table->string('block_reason')->nullable();
            $table->json('raw_snapshot')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('imported_at')->nullable();
            $table->unsignedBigInteger('imported_by_admin_id')->nullable();
            $table->unsignedBigInteger('mapped_customer_id')->nullable();
            $table->text('import_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_customer_inbox');
    }
};
