<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('document_type', ['passport', 'nrc', 'drivers_license', 'voters_card', 'other'])->default('nrc');
            $table->string('front_image_path');
            $table->string('back_image_path')->nullable();
            $table->string('profile_picture_path')->nullable();
            $table->string('bank_statement_path')->nullable();
            $table->string('payslip_path')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('admins')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
