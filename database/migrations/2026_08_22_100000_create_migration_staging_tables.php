<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phase')->default('m1');
            $table->string('scope')->nullable();
            $table->string('status')->default('pending');
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_client_id');
            $table->unsignedBigInteger('mapped_company_id')->nullable();
            $table->string('match_strategy')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
            $table->unique(['migration_run_id', 'legacy_client_id']);
        });

        Schema::create('migration_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_user_id');
            $table->unsignedBigInteger('legacy_customer_id')->nullable();
            $table->unsignedBigInteger('legacy_client_id')->nullable();
            $table->unsignedBigInteger('mapped_customer_id')->nullable();
            $table->string('target_product_code')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('completeness')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
            $table->unique(['migration_run_id', 'legacy_user_id']);
        });

        Schema::create('migration_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_loan_id');
            $table->unsignedBigInteger('legacy_user_id');
            $table->unsignedBigInteger('mapped_loan_id')->nullable();
            $table->string('legacy_product_type')->nullable();
            $table->string('target_product_code')->nullable();
            $table->decimal('legacy_effective_outstanding', 15, 2)->nullable();
            $table->decimal('target_outstanding', 15, 2)->nullable();
            $table->decimal('balance_variance', 15, 2)->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
            $table->unique(['migration_run_id', 'legacy_loan_id']);
        });

        Schema::create('migration_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_repayment_id');
            $table->unsignedBigInteger('legacy_user_id');
            $table->unsignedBigInteger('mapped_repayment_id')->nullable();
            $table->string('attribution_class')->nullable();
            $table->decimal('repayment_amount', 15, 2)->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('allocations')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
            $table->unique(['migration_run_id', 'legacy_repayment_id']);
        });

        Schema::create('migration_financial_institutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->string('source_system')->default('legacy_finedge');
            $table->string('legacy_identifier');
            $table->unsignedBigInteger('legacy_bank_id')->nullable();
            $table->unsignedBigInteger('mapped_financial_institution_id')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_user_id');
            $table->unsignedBigInteger('legacy_customer_id')->nullable();
            $table->unsignedBigInteger('mapped_customer_id')->nullable();
            $table->unsignedBigInteger('mapped_payment_detail_id')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_wallet_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->string('source_system')->default('legacy_finedge');
            $table->string('legacy_identifier');
            $table->unsignedBigInteger('legacy_wallet_id')->nullable();
            $table->unsignedBigInteger('mapped_wallet_provider_id')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_user_id');
            $table->unsignedBigInteger('legacy_customer_id')->nullable();
            $table->unsignedBigInteger('mapped_customer_id')->nullable();
            $table->string('wallet_number')->nullable();
            $table->string('wallet_number_normalized')->nullable();
            $table->string('provider_code')->nullable();
            $table->string('inferred_from')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_wallets');
        Schema::dropIfExists('migration_wallet_providers');
        Schema::dropIfExists('migration_bank_accounts');
        Schema::dropIfExists('migration_financial_institutions');
        Schema::dropIfExists('migration_repayments');
        Schema::dropIfExists('migration_loans');
        Schema::dropIfExists('migration_customers');
        Schema::dropIfExists('migration_companies');
        Schema::dropIfExists('migration_runs');
    }
};
