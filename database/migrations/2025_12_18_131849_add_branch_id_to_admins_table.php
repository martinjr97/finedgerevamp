<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');

            $table->foreign('branch_id', 'admins_branch_fk')
                ->references('id')
                ->on('branches')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        // Default all existing admins to Head Office branch if it exists
        $headOfficeId = DB::table('branches')
            ->where('code', 'HEAD_OFFICE')
            ->value('id');

        if ($headOfficeId) {
            DB::table('admins')
                ->whereNull('branch_id')
                ->update(['branch_id' => $headOfficeId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropForeign('admins_branch_fk');
            $table->dropColumn('branch_id');
        });
    }
};
