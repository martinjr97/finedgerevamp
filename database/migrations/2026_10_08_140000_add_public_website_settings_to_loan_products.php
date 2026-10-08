<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('loan_products', 'is_public_on_website')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->boolean('is_public_on_website')->default(false)->after('is_active');
            });
        }

        if (! Schema::hasColumn('loan_products', 'public_website_loan_rate_type_id')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->foreignId('public_website_loan_rate_type_id')
                    ->nullable()
                    ->after('is_public_on_website')
                    ->constrained('loan_rate_types')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('loan_product_public_website_rate')) {
            Schema::create('loan_product_public_website_rate', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('loan_rate_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['loan_product_id', 'loan_rate_id'], 'lp_pub_website_rate_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_product_public_website_rate');

        Schema::table('loan_products', function (Blueprint $table) {
            if (Schema::hasColumn('loan_products', 'public_website_loan_rate_type_id')) {
                $table->dropConstrainedForeignId('public_website_loan_rate_type_id');
            }
            if (Schema::hasColumn('loan_products', 'is_public_on_website')) {
                $table->dropColumn('is_public_on_website');
            }
        });
    }
};
