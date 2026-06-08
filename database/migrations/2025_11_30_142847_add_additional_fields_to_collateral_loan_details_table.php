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
        Schema::table('collateral_loan_details', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('collateral_description');
            $table->integer('item_quantity')->nullable()->default(1)->after('serial_number');
            $table->string('item_condition')->nullable()->after('item_quantity')->comment('Condition/neatness of the item');
            $table->boolean('is_inspected')->default(false)->after('item_condition');
            $table->foreignId('inspected_by')->nullable()->after('is_inspected')->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('inspected_at')->nullable()->after('inspected_by');
            $table->string('location')->nullable()->after('inspected_at')->comment('Location of the collateral');
            $table->json('images')->nullable()->after('location')->comment('Array of image paths');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collateral_loan_details', function (Blueprint $table) {
            $table->dropForeign(['inspected_by']);
            $table->dropColumn([
                'serial_number',
                'item_quantity',
                'item_condition',
                'is_inspected',
                'inspected_by',
                'inspected_at',
                'location',
                'images',
            ]);
        });
    }
};
