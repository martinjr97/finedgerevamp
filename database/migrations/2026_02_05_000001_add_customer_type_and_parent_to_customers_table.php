<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_type', 20)->default('individual')->after('loan_product_id');
            $table->unsignedBigInteger('parent_customer_id')->nullable()->after('customer_type');
            $table->string('registered_name')->nullable()->after('last_name');

            $table->foreign('parent_customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['customer_type', 'parent_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['parent_customer_id']);
            $table->dropIndex(['customer_type', 'parent_customer_id']);
            $table->dropColumn(['customer_type', 'parent_customer_id', 'registered_name']);
        });
    }
};
