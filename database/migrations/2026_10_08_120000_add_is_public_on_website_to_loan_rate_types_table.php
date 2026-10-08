<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_rate_types', function (Blueprint $table) {
            $table->boolean('is_public_on_website')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('loan_rate_types', function (Blueprint $table) {
            $table->dropColumn('is_public_on_website');
        });
    }
};
