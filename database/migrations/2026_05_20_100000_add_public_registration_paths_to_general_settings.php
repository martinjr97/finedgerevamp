<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('general_settings', 'public_registration_paths')) {
                $table->json('public_registration_paths')->nullable()->after('public_registration_group_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('general_settings', 'public_registration_paths')) {
                $table->dropColumn('public_registration_paths');
            }
        });
    }
};
