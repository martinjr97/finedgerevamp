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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();
            $table->enum('type', ['operator', 'partner'])->default('partner');
            $table->string('registration_number')->nullable();
            $table->date('date_of_incorporation')->nullable();
            $table->foreignId('sector_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            // Note: relationship_manager_id foreign key will be added after admins table is created
            $table->unsignedBigInteger('relationship_manager_id')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->enum('status', ['pending', 'active', 'suspended'])->default('active');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved');
            // Note: approved_by foreign key will be added after admins table is created
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
