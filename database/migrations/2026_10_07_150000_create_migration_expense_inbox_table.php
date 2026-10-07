<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_expense_inbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_expense_id')->unique();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('status')->default('pending_sync');
            $table->string('sync_error')->nullable();
            $table->json('raw_snapshot')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('mapped_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_expense_inbox');
    }
};
