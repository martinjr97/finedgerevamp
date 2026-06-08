<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to add 'settled' status
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending_approval', 'approved', 'active', 'completed', 'settled', 'defaulted', 'cancelled') DEFAULT 'pending_approval'");
        
        // Update existing 'completed' loans to 'settled' if they have outstanding_balance = 0 and loan_settled_date is set
        DB::statement("UPDATE loans SET status = 'settled' WHERE status = 'completed' AND outstanding_balance = 0 AND loan_settled_date IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update 'settled' loans back to 'completed' before removing the enum value
        DB::statement("UPDATE loans SET status = 'completed' WHERE status = 'settled'");
        
        // Revert the enum back to original values
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending_approval', 'approved', 'active', 'completed', 'defaulted', 'cancelled') DEFAULT 'pending_approval'");
    }
};
