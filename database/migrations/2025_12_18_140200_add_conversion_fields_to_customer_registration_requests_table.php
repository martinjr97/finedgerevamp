<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_registration_requests', function (Blueprint $table): void {
            $table->foreignId('created_customer_id')
                ->nullable()
                ->after('status')
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->after('created_customer_id')
                ->constrained('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('created_customer_at')
                ->nullable()
                ->after('created_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_registration_requests', function (Blueprint $table): void {
            $table->dropForeign(['created_customer_id']);
            $table->dropForeign(['created_by_admin_id']);
            $table->dropColumn(['created_customer_id', 'created_by_admin_id', 'created_customer_at']);
        });
    }
};


