<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand enum to include company_documents
        DB::statement("ALTER TABLE kyc_documents MODIFY COLUMN document_type ENUM('passport','nrc','drivers_license','voters_card','other','company_documents') NOT NULL DEFAULT 'nrc'");
    }

    public function down(): void
    {
        // Revert enum (will fail if rows contain company_documents); ensure cleanup before rollback
        DB::statement("ALTER TABLE kyc_documents MODIFY COLUMN document_type ENUM('passport','nrc','drivers_license','voters_card','other') NOT NULL DEFAULT 'nrc'");
    }
};
