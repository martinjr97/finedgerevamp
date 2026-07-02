<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropUnique(['account_number']);
            $table->unique(['bank_name', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropUnique(['bank_name', 'account_number']);
            $table->unique('account_number');
        });
    }
};
