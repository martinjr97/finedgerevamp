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
        // Update existing customers that were auto-approved but should be pending
        // If approval is required and the customer hasn't been explicitly approved by an admin,
        // set their approval_status to 'pending'
        $requiresApproval = config('approval.customers.create', false);
        
        if ($requiresApproval) {
            \DB::table('customers')
                ->where('approval_status', 'approved')
                ->whereNull('approved_by')
                ->update(['approval_status' => 'pending']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is data-only, no schema changes to reverse
    }
};
