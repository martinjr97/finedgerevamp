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
        Schema::table('communications', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('metadata')->comment('Whether this communication contains sensitive information (OTP, PIN, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn('is_sensitive');
        });
    }
};
