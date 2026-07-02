<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider_class');
            $table->string('type'); // collection, disbursement, both
            $table->string('status')->default('inactive'); // active, inactive, maintenance, disabled
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_collections')->default(false);
            $table->boolean('supports_disbursements')->default(false);
            $table->boolean('supports_mobile_money')->default(false);
            $table->boolean('supports_bank')->default(false);
            $table->boolean('supports_callbacks')->default(false);
            $table->boolean('supports_polling')->default(false);
            $table->string('financial_account_type')->nullable(); // bank, wallet
            $table->unsignedBigInteger('financial_account_id')->nullable();
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['financial_account_type', 'financial_account_id'], 'pg_financial_account_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
