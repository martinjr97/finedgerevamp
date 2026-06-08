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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_score', 5, 2)->nullable()->after('maximum_loan_take')->comment('Internal credit score (0-100)');
            $table->timestamp('credit_score_updated_at')->nullable()->after('credit_score')->comment('When the credit score was last calculated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['credit_score', 'credit_score_updated_at']);
        });
    }
};
