<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add foreign keys that have circular dependencies
     */
    public function up(): void
    {
        // Add foreign key from companies to admins (relationship_manager_id)
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('relationship_manager_id')->references('id')->on('admins')->cascadeOnUpdate()->nullOnDelete();
        });

        // Add foreign key from companies to admins (approved_by)
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('approved_by')->references('id')->on('admins')->nullOnDelete();
        });

        // Add foreign key from admins to admins (approved_by - self-referencing)
        Schema::table('admins', function (Blueprint $table) {
            $table->foreign('approved_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['relationship_manager_id']);
            $table->dropForeign(['approved_by']);
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
        });
    }
};

