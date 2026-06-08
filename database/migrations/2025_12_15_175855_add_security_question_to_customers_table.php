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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('security_question_id')->nullable()->after('must_change_pin')->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->text('security_answer')->nullable()->after('security_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['security_question_id']);
            $table->dropColumn(['security_question_id', 'security_answer']);
        });
    }
};
