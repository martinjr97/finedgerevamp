<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_loan_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_loan_applications', 'relationship_manager_id')) {
                $table->foreignId('relationship_manager_id')
                    ->nullable()
                    ->after('customer_group_id')
                    ->constrained('admins')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_loan_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('group_loan_applications', 'relationship_manager_id')) {
                $table->dropConstrainedForeignId('relationship_manager_id');
            }
        });
    }
};
