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
        Schema::create('duplicate_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('duplicate_customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('match_type'); // same_nrc, same_phone, same_bank_account, same_device_ip
            $table->string('match_value')->nullable(); // The actual value that matched (IP, device name, etc.)
            $table->text('notes')->nullable(); // Optional notes from the admin who cleared it
            $table->foreignId('cleared_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            // Ensure we don't have duplicate entries
            $table->unique(['customer_id', 'duplicate_customer_id', 'match_type', 'match_value'], 'unique_duplicate_alert');
            $table->index(['customer_id', 'cleared_at']);
            $table->index(['duplicate_customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_alerts');
    }
};
