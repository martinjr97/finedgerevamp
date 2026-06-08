<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_payment_schedules', function (Blueprint $table): void {
            $table->boolean('is_restructured')->default(false)->after('days_overdue');
            $table->timestamp('restructured_at')->nullable()->after('is_restructured');
            $table->foreignId('loan_extension_id')
                ->nullable()
                ->after('restructured_at')
                ->constrained('loan_extensions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['loan_id', 'is_restructured'], 'loan_schedules_loan_restructured_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payment_schedules', function (Blueprint $table): void {
            $table->dropIndex('loan_schedules_loan_restructured_index');
            $table->dropConstrainedForeignId('loan_extension_id');
            $table->dropColumn(['is_restructured', 'restructured_at']);
        });
    }
};
