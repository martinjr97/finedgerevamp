<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, convert existing date values to day of month using a temporary varchar column
        Schema::table('customers', function (Blueprint $table) {
            $table->string('payday_temp', 2)->nullable()->after('payday');
        });
        
        // Extract day from date column and store in temp column
        // Since payday is a DATE column, we can use DAY() directly
        DB::statement("UPDATE customers SET payday_temp = LPAD(DAY(payday), 2, '0') WHERE payday IS NOT NULL");
        
        // Drop the old column
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('payday');
        });
        
        // Rename temp column to payday and change to tinyInteger
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('payday_temp', 'payday');
        });
        
        Schema::table('customers', function (Blueprint $table) {
            $table->tinyInteger('payday')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Revert back to date type
            $table->date('payday')->nullable()->change();
        });
        
        // Note: We cannot perfectly restore the original dates, so we'll set them to the 1st of current month
        DB::statement('UPDATE customers SET payday = DATE_FORMAT(CURDATE(), CONCAT("%Y-%m-", IF(payday IS NULL OR payday = 0, "01", LPAD(payday, 2, "0")))) WHERE payday IS NOT NULL');
    }
};
