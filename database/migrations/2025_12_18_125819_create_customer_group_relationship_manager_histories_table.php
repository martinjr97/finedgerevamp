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
        Schema::dropIfExists('customer_group_relationship_manager_histories');

        Schema::create('customer_group_relationship_manager_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_group_id');
            $table->unsignedBigInteger('relationship_manager_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('change_reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_group_id', 'cg_rm_hist_group_fk')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('relationship_manager_id', 'cg_rm_hist_rm_fk')
                ->references('id')
                ->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('changed_by', 'cg_rm_hist_changed_by_fk')
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
        Schema::dropIfExists('customer_group_relationship_manager_histories');
    }
};
