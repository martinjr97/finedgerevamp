<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_identity_resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('nrc');
            $table->unsignedBigInteger('primary_legacy_user_id');
            $table->json('alias_legacy_user_ids')->nullable();
            $table->json('excluded_legacy_user_ids')->nullable();
            $table->unsignedBigInteger('target_customer_id')->nullable();
            $table->string('classification', 64);
            $table->string('status', 32)->default('approved');
            $table->text('reason')->nullable();
            $table->json('legacy_context')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique('nrc');
            $table->foreign('decided_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_identity_resolutions');
    }
};
