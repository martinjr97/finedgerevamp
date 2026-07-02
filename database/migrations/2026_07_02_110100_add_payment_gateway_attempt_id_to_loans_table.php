<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('payment_gateway_attempt_id')
                ->nullable()
                ->after('disbursement_reference')
                ->constrained('payment_gateway_attempts')
                ->nullOnDelete();

            $table->index('payment_gateway_attempt_id', 'loans_pg_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['payment_gateway_attempt_id']);
            $table->dropIndex('loans_pg_attempt_idx');
            $table->dropColumn('payment_gateway_attempt_id');
        });
    }
};
