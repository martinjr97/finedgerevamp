<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1: pricing architecture columns (non-breaking).
     *
     * Existing loans continue to use frozen snapshot fields (daily_rate, total_amount, etc.).
     * New columns are nullable or defaulted so legacy rate types and open loans are unaffected.
     */
    public function up(): void
    {
        Schema::table('loan_rate_types', function (Blueprint $table) {
            $table->enum('interest_behavior', ['daily_accrual', 'upfront_flat', 'amortized'])
                ->default('daily_accrual')
                ->after('accrual_period')
                ->comment('How interest is applied for loans using this rate type');

            $table->enum('rate_input_mode', ['daily_multiplier', 'weekly_multiplier', 'term_percentage'])
                ->default('daily_multiplier')
                ->after('interest_behavior')
                ->comment('How administrators enter rates in the grid/import');
        });

        // Align legacy weekly rate plans with weekly multiplier input mode.
        DB::table('loan_rate_types')
            ->where('accrual_period', 'weekly')
            ->update(['rate_input_mode' => 'weekly_multiplier']);

        Schema::table('loan_rates', function (Blueprint $table) {
            $table->decimal('term_interest_percentage', 8, 4)
                ->nullable()
                ->after('processing_fee_percentage')
                ->comment('Business term rate for the tenure, e.g. 27.8000 = 27.8% total cost for the term');

            $table->decimal('min_principal', 14, 2)
                ->nullable()
                ->after('term_interest_percentage')
                ->comment('Inclusive minimum principal for this rate row; null = no lower bound');

            $table->decimal('max_principal', 14, 2)
                ->nullable()
                ->after('min_principal')
                ->comment('Inclusive maximum principal for this rate row; null = no upper bound');

            $table->decimal('derived_daily_rate', 10, 8)
                ->nullable()
                ->after('weekly_rate')
                ->comment('Cached daily factor from term rate; used when rate_input_mode = term_percentage');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('quoted_term_rate', 8, 4)
                ->nullable()
                ->after('weekly_rate')
                ->comment('Term interest % quoted at loan creation, frozen on the loan');

            $table->string('interest_behavior', 32)
                ->nullable()
                ->after('accrual_type')
                ->comment('Frozen interest behavior: daily_accrual, upfront_flat, amortized');

            $table->decimal('settlement_amount', 14, 2)
                ->nullable()
                ->after('loan_settled_date')
                ->comment('Final settlement payoff amount when formally closed early');

            $table->date('settlement_date')
                ->nullable()
                ->after('settlement_amount')
                ->comment('Date settlement quote was accepted or posted');

            $table->decimal('rebate_amount', 14, 2)
                ->nullable()
                ->after('settlement_date')
                ->comment('Unearned interest/fees credited on early settlement');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'quoted_term_rate',
                'interest_behavior',
                'settlement_amount',
                'settlement_date',
                'rebate_amount',
            ]);
        });

        Schema::table('loan_rates', function (Blueprint $table) {
            $table->dropColumn([
                'term_interest_percentage',
                'min_principal',
                'max_principal',
                'derived_daily_rate',
            ]);
        });

        Schema::table('loan_rate_types', function (Blueprint $table) {
            $table->dropColumn(['interest_behavior', 'rate_input_mode']);
        });
    }
};
