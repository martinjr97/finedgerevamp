<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_repayment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_repayment_id');
            $table->unsignedBigInteger('legacy_loan_id');
            $table->unsignedBigInteger('legacy_user_id');
            $table->decimal('allocated_amount', 15, 2);
            $table->decimal('principal_amount', 15, 2)->nullable();
            $table->decimal('interest_amount', 15, 2)->nullable();
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->decimal('penalty_amount', 15, 2)->nullable();
            $table->string('classification')->nullable();
            $table->string('confidence')->default('HIGH');
            $table->string('rule_used')->nullable();
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
            $table->index(['migration_run_id', 'legacy_repayment_id'], 'migr_repay_alloc_run_repay_idx');
            $table->index(['migration_run_id', 'legacy_loan_id'], 'migr_repay_alloc_run_loan_idx');
        });

        Schema::create('migration_loan_replay_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_loan_id');
            $table->unsignedBigInteger('legacy_user_id');
            $table->string('product_class')->nullable();
            $table->decimal('legacy_effective_outstanding', 15, 2);
            $table->decimal('replayed_cash_total', 15, 2)->default(0);
            $table->decimal('replayed_principal', 15, 2)->default(0);
            $table->decimal('replayed_interest', 15, 2)->default(0);
            $table->decimal('simulated_outstanding', 15, 2)->default(0);
            $table->decimal('migration_opening_adjustment', 15, 2)->default(0);
            $table->decimal('reconstructed_outstanding', 15, 2)->default(0);
            $table->decimal('variance', 15, 2)->default(0);
            $table->string('reconciliation_status')->default('pending');
            $table->string('promotion_status')->default('pending');
            $table->json('raw_context')->nullable();
            $table->timestamps();
            $table->unique(['migration_run_id', 'legacy_loan_id'], 'migr_loan_replay_run_loan_uq');
        });

        Schema::create('migration_customer_replay_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_user_id');
            $table->decimal('legacy_sum_effective', 15, 2);
            $table->decimal('reconstructed_sum', 15, 2);
            $table->decimal('variance', 15, 2);
            $table->string('reconciliation_status');
            $table->timestamps();
            $table->unique(['migration_run_id', 'legacy_user_id'], 'migr_cust_replay_run_user_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_customer_replay_results');
        Schema::dropIfExists('migration_loan_replay_results');
        Schema::dropIfExists('migration_repayment_allocations');
    }
};
