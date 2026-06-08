<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->string('recovery_method', 50)
                ->default('normal')
                ->after('total_amount')
                ->comment('How or why the repayment was recovered or initiated');
        });
    }

    public function down(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->dropColumn('recovery_method');
        });
    }
};
