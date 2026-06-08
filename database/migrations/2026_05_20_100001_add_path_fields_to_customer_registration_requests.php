<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_registration_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_registration_requests', 'registration_path')) {
                $table->string('registration_path')->nullable()->after('reference');
            }
            if (! Schema::hasColumn('customer_registration_requests', 'requested_loan_amount')) {
                $table->decimal('requested_loan_amount', 14, 2)->nullable()->after('tpin');
            }
            if (! Schema::hasColumn('customer_registration_requests', 'employment_details')) {
                $table->json('employment_details')->nullable()->after('payload');
            }
            if (! Schema::hasColumn('customer_registration_requests', 'collateral_details')) {
                $table->json('collateral_details')->nullable()->after('employment_details');
            }
            if (! Schema::hasColumn('customer_registration_requests', 'approval_metadata')) {
                $table->json('approval_metadata')->nullable()->after('collateral_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_registration_requests', function (Blueprint $table): void {
            $columns = ['registration_path', 'requested_loan_amount', 'employment_details', 'collateral_details', 'approval_metadata'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('customer_registration_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
