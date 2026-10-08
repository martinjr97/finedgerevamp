<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('title')->nullable()->after('employee_number');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('gender', 20)->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('national_id')->nullable()->after('date_of_birth');
            $table->string('nationality')->nullable()->after('national_id');
            $table->string('marital_status', 30)->nullable()->after('nationality');
            $table->string('alternative_phone')->nullable()->after('phone');
            $table->string('personal_email')->nullable()->after('email');
            $table->text('residential_address')->nullable()->after('personal_email');
            $table->text('postal_address')->nullable()->after('residential_address');
            $table->string('profile_photo_path')->nullable()->after('postal_address');
            $table->string('employment_status', 30)->default('active')->after('department');
            $table->unsignedBigInteger('department_id')->nullable()->after('employment_status');
            $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            $table->unsignedBigInteger('reports_to_employee_id')->nullable()->after('position_id');
            $table->date('date_joined')->nullable()->after('reports_to_employee_id');
            $table->date('employment_start_date')->nullable()->after('date_joined');
            $table->string('work_location')->nullable()->after('employment_start_date');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedBigInteger('head_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('reports_to_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->boolean('has_end_date')->default(true);
            $table->unsignedSmallInteger('default_duration_months')->nullable();
            $table->boolean('leave_accrual_enabled')->default(true);
            $table->decimal('leave_days_per_month', 5, 2)->nullable();
            $table->unsignedSmallInteger('probation_months')->nullable();
            $table->boolean('renewable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('accrual_based')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contract_type_leave_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('days_per_month', 5, 2)->default(0);
            $table->decimal('annual_cap', 8, 2)->nullable();
            $table->boolean('carry_forward_allowed')->default(false);
            $table->decimal('maximum_carry_forward', 8, 2)->nullable();
            $table->boolean('requires_accrual')->default(true);
            $table->timestamps();
            $table->unique(['contract_type_id', 'leave_type_id'], 'contract_leave_rule_unique');
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_type_id')->constrained();
            $table->string('contract_number')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('basic_pay_snapshot', 15, 2)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('probation_end_date')->nullable();
            $table->date('signed_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'status']);
            $table->index('end_date');
        });

        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_pay', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('currency', 3)->default('ZMW');
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'is_current']);
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('account_type', 30)->nullable();
            $table->string('currency', 3)->default('ZMW');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('employee_id');
        });

        Schema::create('employee_next_of_kin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship');
            $table->string('phone_number')->nullable();
            $table->string('alternative_phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('employee_dependants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('national_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 8, 2);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approver_comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('employee_leave_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('transaction_date');
            $table->string('type', 30);
            $table->decimal('days', 8, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'leave_type_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('title');
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_leave_transactions');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('employee_dependants');
        Schema::dropIfExists('employee_next_of_kin');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_compensations');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('contract_type_leave_rules');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('contract_types');

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropForeign(['reports_to_employee_id']);
        });

        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'middle_name', 'gender', 'date_of_birth', 'national_id', 'nationality',
                'marital_status', 'alternative_phone', 'personal_email', 'residential_address',
                'postal_address', 'profile_photo_path', 'employment_status', 'department_id',
                'position_id', 'reports_to_employee_id', 'date_joined', 'employment_start_date',
                'work_location',
            ]);
        });
    }
};
