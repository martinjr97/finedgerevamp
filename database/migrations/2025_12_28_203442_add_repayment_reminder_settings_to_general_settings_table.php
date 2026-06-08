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
        Schema::table('general_settings', function (Blueprint $table) {
            // Enable/disable repayment reminders
            $table->boolean('repayment_reminders_enabled')->default(false)->after('public_registration_group_ids');
            
            // Upcoming payment reminders (days before due date)
            $table->boolean('remind_1_week_before')->default(false)->after('repayment_reminders_enabled');
            $table->boolean('remind_2_days_before')->default(false)->after('remind_1_week_before');
            $table->boolean('remind_1_day_before')->default(false)->after('remind_2_days_before');
            
            // Missed payment reminders (how many to send)
            $table->integer('missed_payment_reminder_count')->default(0)->comment('Number of reminders to send for missed payments (0, 1, or 2)')->after('remind_1_day_before');
            
            // Track which reminders have been sent (to avoid duplicates)
            // This will be stored in a separate table: repayment_reminder_logs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'repayment_reminders_enabled',
                'remind_1_week_before',
                'remind_2_days_before',
                'remind_1_day_before',
                'missed_payment_reminder_count',
            ]);
        });
    }
};
