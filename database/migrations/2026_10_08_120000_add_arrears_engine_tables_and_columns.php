<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('arrear_rate', 8, 5)
                ->nullable()
                ->after('weekly_rate')
                ->comment('Daily arrear factor snapshotted at origination (same semantics as loan_rates.arrear_rate, e.g. 0.01 = 1% per day)');

            $table->date('arrears_last_accrual_date')
                ->nullable()
                ->after('last_accrual_date')
                ->comment('Last calendar date processed by the arrears engine');

            $table->string('performance_status', 32)
                ->default('performing')
                ->after('status')
                ->comment('Portfolio performance classification e.g. performing, npl');

            $table->timestamp('npl_at')
                ->nullable()
                ->after('performance_status')
                ->comment('When the loan was first classified as NPL');
        });

        Schema::create('loan_arrears_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('loan_payment_schedule_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('accrual_date');
            $table->decimal('arrear_rate', 8, 5);
            $table->decimal('opening_overdue_amount', 14, 2);
            $table->decimal('arrears_charge', 14, 2);
            $table->unsignedSmallInteger('days_overdue_on_date')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['loan_payment_schedule_id', 'accrual_date'], 'loan_schedule_arrears_accrual_unique');
            $table->index(['loan_id', 'accrual_date']);
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->decimal('arrears_interest_amount', 14, 2)
                ->default(0)
                ->after('processing_fee_amount')
                ->comment('Portion applied to outstanding arrears interest');
        });

        // Snapshot arrear_rate from linked loan_rate where possible (does not create historical accruals).
        if (Schema::hasTable('loans') && Schema::hasTable('loan_rates')) {
            DB::table('loans')
                ->whereNull('arrear_rate')
                ->whereNotNull('loan_rate_id')
                ->orderBy('id')
                ->chunkById(200, function ($loans) {
                    foreach ($loans as $loan) {
                        $rate = DB::table('loan_rates')->where('id', $loan->loan_rate_id)->value('arrear_rate');
                        if ($rate !== null) {
                            DB::table('loans')->where('id', $loan->id)->update(['arrear_rate' => $rate]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn('arrears_interest_amount');
        });

        Schema::dropIfExists('loan_arrears_accruals');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'arrear_rate',
                'arrears_last_accrual_date',
                'performance_status',
                'npl_at',
            ]);
        });
    }
};
