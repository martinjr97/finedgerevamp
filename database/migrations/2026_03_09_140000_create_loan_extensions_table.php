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
        Schema::create('loan_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('extension_type')->comment('1=due_date_extension,2=interest_rollover,3=restructure');
            $table->unsignedTinyInteger('interest_mode')->comment('1=configured_rate,2=custom_rate,3=fixed_amount');
            $table->decimal('interest_rate', 12, 6)->nullable();
            $table->decimal('interest_amount', 14, 2)->default(0);
            $table->date('old_due_date')->nullable();
            $table->date('new_due_date')->nullable();
            $table->string('extension_period', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'created_at']);
            $table->index('extension_type');
            $table->index('interest_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_extensions');
    }
};
