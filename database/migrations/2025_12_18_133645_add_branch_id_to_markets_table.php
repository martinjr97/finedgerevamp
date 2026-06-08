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
        Schema::table('markets', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('district_id');

            $table->foreign('branch_id', 'markets_branch_fk')
                ->references('id')
                ->on('branches')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropForeign('markets_branch_fk');
            $table->dropColumn('branch_id');
        });
    }
};
