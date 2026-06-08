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
        Schema::table('repayments', function (Blueprint $table) {
            $table->enum('received_via_type', ['bank', 'wallet'])->nullable()->after('channel_id');
            $table->unsignedBigInteger('received_via_id')->nullable()->after('received_via_type');
            
            $table->index(['received_via_type', 'received_via_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repayments', function (Blueprint $table) {
            $table->dropIndex(['received_via_type', 'received_via_id']);
            $table->dropColumn(['received_via_type', 'received_via_id']);
        });
    }
};
