<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_institution_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_institution_id')
                ->constrained('financial_institutions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('sort_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financial_institution_id', 'is_active'], 'fi_branches_institution_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_institution_branches');
    }
};
