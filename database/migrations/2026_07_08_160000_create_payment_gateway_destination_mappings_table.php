<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_destination_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_gateway_id')
                ->constrained('payment_gateways', 'id', 'pgdm_pg_fk')
                ->cascadeOnDelete();

            // Example values: 'bank', 'mobile_money'
            $table->string('destination_type');

            $table->foreignId('financial_institution_id')
                ->nullable()
                ->constrained('financial_institutions', 'id', 'pgdm_fi_fk')
                ->nullOnDelete();

            $table->foreignId('channel_id')
                ->nullable()
                ->constrained('channels', 'id', 'pgdm_ch_fk')
                ->nullOnDelete();

            // Example values for cGrate: 'issuerName'
            $table->string('gateway_key');
            $table->string('gateway_value');

            // Example values: 'local', 'uat', 'production'. Null means "global default".
            $table->string('environment')->nullable();

            // Example values: 'active', 'inactive', 'verification_required'
            $table->string('status')->default('active');

            $table->timestamp('last_verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['payment_gateway_id', 'destination_type'], 'pgdm_pg_dest_idx');
            $table->index(['payment_gateway_id', 'environment', 'status'], 'pgdm_pg_env_status_idx');

            $table->unique(
                [
                    'payment_gateway_id',
                    'destination_type',
                    'financial_institution_id',
                    'channel_id',
                    'environment',
                    'gateway_key',
                    'status',
                ],
                'pgdm_unique_mapping'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_destination_mappings');
    }
};

