<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'national_id_type')) {
                $table->string('national_id_type', 32)->nullable()->after('national_id');
            }
        });

        Schema::table('customer_registration_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_registration_requests', 'national_id_type')) {
                $table->string('national_id_type', 32)->nullable()->after('national_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'national_id_type')) {
                $table->dropColumn('national_id_type');
            }
        });

        Schema::table('customer_registration_requests', function (Blueprint $table) {
            if (Schema::hasColumn('customer_registration_requests', 'national_id_type')) {
                $table->dropColumn('national_id_type');
            }
        });
    }
};
