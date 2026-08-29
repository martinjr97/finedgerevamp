<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('migration_runs', 'run_uuid')) {
                $table->uuid('run_uuid')->nullable()->after('id');
                $table->index('run_uuid');
            }
        });

        Schema::create('migration_entity_maps', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64);
            $table->string('legacy_identifier', 128);
            $table->string('legacy_secondary', 128)->nullable();
            $table->string('target_type', 128);
            $table->unsignedBigInteger('target_id');
            $table->string('mapping_method', 64)->default('migration');
            $table->string('mapping_confidence', 32)->default('HIGH');
            $table->foreignId('migration_run_id')->nullable()->constrained('migration_runs')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['entity_type', 'legacy_identifier', 'legacy_secondary'], 'migr_entity_map_uq');
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('migration_created_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
            $table->string('record_type', 64);
            $table->unsignedBigInteger('record_id');
            $table->timestamps();
            $table->index(['migration_run_id', 'record_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_created_records');
        Schema::dropIfExists('migration_entity_maps');

        Schema::table('migration_runs', function (Blueprint $table) {
            if (Schema::hasColumn('migration_runs', 'run_uuid')) {
                $table->dropIndex(['run_uuid']);
                $table->dropColumn('run_uuid');
            }
        });
    }
};
