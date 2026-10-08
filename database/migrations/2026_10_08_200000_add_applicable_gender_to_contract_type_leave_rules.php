<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_type_leave_rules', function (Blueprint $table) {
            $table->string('applicable_gender', 20)->default('all')->after('requires_accrual');
        });
    }

    public function down(): void
    {
        Schema::table('contract_type_leave_rules', function (Blueprint $table) {
            $table->dropColumn('applicable_gender');
        });
    }
};
