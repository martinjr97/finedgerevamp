<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmec_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('submission_month', 7)->comment('YYYY-MM');
            $table->string('mode', 40);
            $table->string('status', 20)->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index(['loan_product_id', 'submission_month']);
            $table->index('status');
        });

        Schema::create('pmec_submission_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pmec_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('pernr', 50)->nullable();
            $table->string('nrc', 50)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('surname', 100)->nullable();
            $table->date('begda');
            $table->date('endda');
            $table->decimal('betrg', 14, 2);
            $table->string('lgart', 10)->default('8000');
            $table->string('emfsl', 10)->default('F021');
            $table->string('zlsch', 5)->default('E');
            $table->string('status', 20)->default('generated');
            $table->text('failure_reason')->nullable();
            $table->foreignId('previous_submission_item_id')
                ->nullable()
                ->constrained('pmec_submission_items')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmec_submission_items');
        Schema::dropIfExists('pmec_submissions');
    }
};
