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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('loan_product_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('reference_company_id')->nullable()->constrained('companies')->cascadeOnUpdate()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable()->unique();
            $table->string('tpin')->nullable()->unique();
            $table->enum('status', ['pending', 'active', 'suspended', 'closed'])->default('pending');
            $table->enum('kyc_status', ['unverified', 'in_review', 'verified', 'rejected'])->default('unverified');
            $table->string('employment_status')->nullable();
            // Government-specific fields
            // Note: ministry_id foreign key will be added after ministries table is created
            $table->unsignedBigInteger('ministry_id')->nullable();
            $table->date('date_of_employment')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->decimal('gross_salary', 12, 2)->nullable();
            $table->decimal('net_salary', 12, 2)->nullable();
            $table->decimal('deductions', 12, 2)->nullable();
            $table->decimal('maximum_loan_take', 14, 2)->default(0.00);
            // Note: verified_by foreign key will be added after admins table is created
            $table->unsignedBigInteger('verified_by')->nullable();
            // Customer address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            // Work address
            $table->string('work_address_line1')->nullable();
            $table->string('work_address_line2')->nullable();
            $table->string('work_city')->nullable();
            // Note: work_province_id and work_district_id foreign keys will be added after provinces/districts tables are created
            $table->unsignedBigInteger('work_province_id')->nullable();
            $table->unsignedBigInteger('work_district_id')->nullable();
            $table->string('work_postal_code')->nullable();
            $table->string('work_country')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('preferred_language')->default('en');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->boolean('must_change_pin')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
