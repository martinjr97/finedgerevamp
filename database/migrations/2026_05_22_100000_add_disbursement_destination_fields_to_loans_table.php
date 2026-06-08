<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('disbursement_channel_type', 32)->nullable()->after('channel_id');
            $table->foreignId('disbursement_financial_institution_id')
                ->nullable()
                ->after('disbursement_channel_type')
                ->constrained('financial_institutions')
                ->nullOnDelete();
            $table->foreignId('disbursement_financial_institution_branch_id')
                ->nullable()
                ->after('disbursement_financial_institution_id')
                ->constrained('financial_institution_branches')
                ->nullOnDelete();
            $table->string('disbursement_account_holder_name')->nullable()->after('disbursement_financial_institution_branch_id');
            $table->string('disbursement_account_number', 50)->nullable()->after('disbursement_account_holder_name');
            $table->json('disbursement_destination_snapshot')->nullable()->after('disbursement_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['disbursement_financial_institution_branch_id']);
            $table->dropForeign(['disbursement_financial_institution_id']);
            $table->dropColumn([
                'disbursement_channel_type',
                'disbursement_financial_institution_id',
                'disbursement_financial_institution_branch_id',
                'disbursement_account_holder_name',
                'disbursement_account_number',
                'disbursement_destination_snapshot',
            ]);
        });
    }
};
