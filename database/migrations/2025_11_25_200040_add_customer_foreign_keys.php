<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add foreign keys for customers table after related tables are created
     */
    public function up(): void
    {
        // Add foreign key from customers to ministries
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('ministry_id')->references('id')->on('ministries')->cascadeOnUpdate()->nullOnDelete();
        });

        // Add foreign key from customers to admins (verified_by)
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('verified_by')->references('id')->on('admins')->cascadeOnUpdate()->nullOnDelete();
        });

        // Add foreign keys from customers to provinces and districts
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('work_province_id')->references('id')->on('provinces')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('work_district_id')->references('id')->on('districts')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['ministry_id']);
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['work_province_id']);
            $table->dropForeign(['work_district_id']);
        });
    }
};

