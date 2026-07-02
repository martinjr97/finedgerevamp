<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->string('issuer_name')->nullable()->after('customer_phone');
            $table->string('customer_account')->nullable()->after('issuer_name');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->dropColumn(['issuer_name', 'customer_account']);
        });
    }
};
