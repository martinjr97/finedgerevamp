<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->string('legacy_identifier');
            $table->unsignedBigInteger('mapped_branch_id')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('candidate_target_ids')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();

            $table->unique(['migration_run_id', 'legacy_identifier'], 'migration_branches_run_legacy_uq');
        });

        Schema::create('migration_relationship_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_relationship_manager_id');
            $table->unsignedBigInteger('mapped_admin_id')->nullable();
            $table->unsignedBigInteger('mapped_branch_id')->nullable();
            $table->string('migration_status')->default('pending');
            $table->string('confidence')->default('HIGH');
            $table->string('exception')->nullable();
            $table->json('candidate_target_ids')->nullable();
            $table->json('raw_context')->nullable();
            $table->timestamps();

            $table->unique(['migration_run_id', 'legacy_relationship_manager_id'], 'migration_rm_run_legacy_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_relationship_managers');
        Schema::dropIfExists('migration_branches');
    }
};
