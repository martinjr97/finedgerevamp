<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->foreignId('payment_gateway_attempt_id')
                ->nullable()
                ->after('channel_id')
                ->constrained('payment_gateway_attempts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_gateway_attempt_id');
        });
    }
};
