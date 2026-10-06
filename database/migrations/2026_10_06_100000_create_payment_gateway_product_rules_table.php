<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_product_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('payment_method');
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('auto_process')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['loan_product_id', 'direction', 'payment_method'],
                'pg_product_rules_unique',
            );
            $table->index(['direction', 'payment_method'], 'pg_product_rules_direction_method_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_product_rules');
    }
};
