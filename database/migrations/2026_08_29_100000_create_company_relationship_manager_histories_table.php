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
        Schema::create('company_relationship_manager_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('relationship_manager_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('change_reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id', 'co_rm_hist_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('relationship_manager_id', 'co_rm_hist_rm_fk')
                ->references('id')
                ->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('changed_by', 'co_rm_hist_changed_by_fk')
                ->references('id')
                ->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('companies')->whereNotNull('relationship_manager_id')->get(['id', 'relationship_manager_id', 'updated_at']) as $company) {
            DB::table('company_relationship_manager_histories')->insert([
                'company_id' => $company->id,
                'relationship_manager_id' => $company->relationship_manager_id,
                'started_at' => $company->updated_at ?? $now,
                'ended_at' => null,
                'change_reason' => 'Existing assignment',
                'changed_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_relationship_manager_histories');
    }
};
