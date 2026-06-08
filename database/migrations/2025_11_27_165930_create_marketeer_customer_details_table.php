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
        Schema::create('marketeer_customer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('stand_number')->nullable();
            $table->text('stand_description')->nullable(); // What they deal with
            $table->decimal('monthly_income', 12, 2)->nullable(); // Net income
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketeer_customer_details');
    }
};
