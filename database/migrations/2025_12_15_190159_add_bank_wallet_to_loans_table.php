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
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('disbursed_via_type', ['bank', 'wallet'])->nullable()->after('disbursement_status');
            $table->unsignedBigInteger('disbursed_via_id')->nullable()->after('disbursed_via_type');
            
            $table->index(['disbursed_via_type', 'disbursed_via_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['disbursed_via_type', 'disbursed_via_id']);
            $table->dropColumn(['disbursed_via_type', 'disbursed_via_id']);
        });
    }
};
