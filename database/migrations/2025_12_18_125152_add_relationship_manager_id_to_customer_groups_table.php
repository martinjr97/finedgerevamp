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
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('relationship_manager_id')
                ->nullable()
                ->after('loan_rate_type_id')
                ->comment('Admin user who manages this customer group');

            $table->foreign('relationship_manager_id')
                ->references('id')
                ->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropForeign(['relationship_manager_id']);
            $table->dropColumn('relationship_manager_id');
        });
    }
};
