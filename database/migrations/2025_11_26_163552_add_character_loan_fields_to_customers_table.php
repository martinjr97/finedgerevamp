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
            // Customer group for character-based loans
            $table->foreignId('customer_group_id')->nullable()->after('loan_product_id')->constrained('customer_groups')->cascadeOnUpdate()->nullOnDelete();
            
            // Next of kin information
            $table->string('next_of_kin_name')->nullable()->after('work_country');
            $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_relationship')->nullable()->after('next_of_kin_phone');
            $table->string('next_of_kin_address_line1')->nullable()->after('next_of_kin_relationship');
            $table->string('next_of_kin_address_line2')->nullable()->after('next_of_kin_address_line1');
            $table->string('next_of_kin_city')->nullable()->after('next_of_kin_address_line2');
            $table->string('next_of_kin_country')->nullable()->after('next_of_kin_city');
            
            // Character-based loan specific work information
            $table->boolean('is_employed')->nullable()->after('next_of_kin_country');
            $table->date('payday')->nullable()->after('is_employed'); // Date they usually receive money
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn([
                'customer_group_id',
                'next_of_kin_name',
                'next_of_kin_phone',
                'next_of_kin_relationship',
                'next_of_kin_address_line1',
                'next_of_kin_address_line2',
                'next_of_kin_city',
                'next_of_kin_country',
                'is_employed',
                'payday',
            ]);
        });
    }
};
