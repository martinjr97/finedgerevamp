<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_loan_inbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_loan_id')->unique();
            $table->unsignedBigInteger('legacy_user_id');
            $table->string('status')->default('pending_review');
            $table->string('block_reason')->nullable();
            $table->json('raw_snapshot')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('imported_at')->nullable();
            $table->unsignedBigInteger('imported_by_admin_id')->nullable();
            $table->unsignedBigInteger('mapped_loan_id')->nullable();
            $table->text('import_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'detected_at']);
            $table->index('legacy_user_id');
        });

        Schema::create('migration_repayment_inbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_repayment_id')->unique();
            $table->unsignedBigInteger('legacy_user_id');
            $table->decimal('repayment_amount', 15, 2)->nullable();
            $table->string('status')->default('pending_sync');
            $table->string('attribution_class')->nullable();
            $table->string('sync_error')->nullable();
            $table->json('raw_snapshot')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('mapped_repayment_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'detected_at']);
            $table->index('legacy_user_id');
        });

        Schema::create('migration_sync_state', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_sync_state');
        Schema::dropIfExists('migration_repayment_inbox');
        Schema::dropIfExists('migration_loan_inbox');
    }
};
