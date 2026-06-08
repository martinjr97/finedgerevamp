<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_loan_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_loan_application_id');
            $table->foreign('group_loan_application_id', 'glad_group_loan_app_fk')
                ->references('id')
                ->on('group_loan_applications')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('document_name');
            $table->string('file_path');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by', 'glad_uploaded_by_fk')
                ->references('id')
                ->on('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->index('group_loan_application_id', 'group_loan_documents_application_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_loan_application_documents');
    }
};
