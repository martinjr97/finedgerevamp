<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->string('disbursement_reference', 100)->nullable()->after('disbursed_by');
            $table->text('disbursement_notes')->nullable()->after('disbursement_reference');
            $table->foreignId('payment_gateway_attempt_id')
                ->nullable()
                ->after('disbursement_notes')
                ->constrained('payment_gateway_attempts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->dropForeign(['payment_gateway_attempt_id']);
            $table->dropColumn(['disbursement_reference', 'disbursement_notes', 'payment_gateway_attempt_id']);
        });
    }
};
