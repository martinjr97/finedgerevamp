<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_payment_schedules', function (Blueprint $table): void {
            $table->decimal('principal_component', 14, 2)->nullable()->after('expected_amount');
            $table->decimal('interest_component', 14, 2)->nullable()->after('principal_component');
            $table->decimal('fee_component', 14, 2)->nullable()->after('interest_component');
            $table->enum('schedule_basis', ['booked_total', 'projected_total'])->nullable()->after('fee_component');
            $table->boolean('is_projected_interest')->default(false)->after('schedule_basis');
        });
    }

    public function down(): void
    {
        Schema::table('loan_payment_schedules', function (Blueprint $table): void {
            $table->dropColumn([
                'principal_component',
                'interest_component',
                'fee_component',
                'schedule_basis',
                'is_projected_interest',
            ]);
        });
    }
};
