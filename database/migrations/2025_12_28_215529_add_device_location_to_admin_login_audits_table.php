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
        Schema::table('admin_login_audits', function (Blueprint $table) {
            $table->string('device_type')->nullable()->after('user_agent'); // mobile, tablet, desktop
            $table->string('device_name')->nullable()->after('device_type'); // e.g., iPhone 13, Samsung Galaxy
            $table->string('browser')->nullable()->after('device_name'); // Chrome, Safari, Firefox
            $table->string('browser_version')->nullable()->after('browser');
            $table->string('os')->nullable()->after('browser_version'); // iOS, Android, Windows, macOS, Linux
            $table->string('os_version')->nullable()->after('os');
            $table->string('location_country')->nullable()->after('ip_address');
            $table->string('location_region')->nullable()->after('location_country');
            $table->string('location_city')->nullable()->after('location_region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_login_audits', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'device_name',
                'browser',
                'browser_version',
                'os',
                'os_version',
                'location_country',
                'location_region',
                'location_city',
            ]);
        });
    }
};
