<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditors', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignId('creditor_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('creditors')
                ->nullOnDelete();
        });

        Schema::create('creditor_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('creditor_id')->constrained('creditors')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('destination_type', 20);
            $table->unsignedBigInteger('destination_id');
            $table->foreignId('financial_transaction_id')
                ->nullable()
                ->constrained('financial_transactions')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditor_conversions');

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creditor_id');
        });

        Schema::table('creditors', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });
    }
};
