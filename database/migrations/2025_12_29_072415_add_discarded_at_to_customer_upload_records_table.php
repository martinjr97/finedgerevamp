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
        Schema::table('customer_upload_records', function (Blueprint $table) {
            $table->timestamp('discarded_at')->nullable()->after('error_message');
            $table->foreignId('discarded_by')->nullable()->after('discarded_at')->constrained('admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_upload_records', function (Blueprint $table) {
            $table->dropForeign(['discarded_by']);
            $table->dropColumn(['discarded_at', 'discarded_by']);
        });
    }
};
