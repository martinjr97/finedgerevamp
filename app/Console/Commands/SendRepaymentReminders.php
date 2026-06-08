<?php

namespace App\Console\Commands;

use App\Models\GeneralSetting;
use App\Models\LoanPaymentSchedule;
use App\Models\Customer;
use App\Models\Communication;
use App\Support\CommunicationLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRepaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repayments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send repayment reminders to customers for upcoming and missed payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting repayment reminder process...');

        // Get settings
        $settings = GeneralSetting::first();
        
        if (!$settings || !$settings->repayment_reminders_enabled) {
            $this->info('Repayment reminders are disabled. Exiting.');
            return 0;
        }

        $sentCount = 0;
        $today = Carbon::today();

        // Send upcoming payment reminders
        if ($settings->remind_1_week_before) {
            $sentCount += $this->sendUpcomingReminders($today->copy()->addDays(7), '1_week_before', $settings);
        }

        if ($settings->remind_2_days_before) {
            $sentCount += $this->sendUpcomingReminders($today->copy()->addDays(2), '2_days_before', $settings);
        }

        if ($settings->remind_1_day_before) {
            $sentCount += $this->sendUpcomingReminders($today->copy()->addDays(1), '1_day_before', $settings);
        }

        // Send missed payment reminders
        if ($settings->missed_payment_reminder_count > 0) {
            $sentCount += $this->sendMissedPaymentReminders($today, $settings);
        }

        $this->info("Repayment reminder process completed. Sent {$sentCount} reminders.");
        return 0;
    }

    /**
     * Send reminders for upcoming payments
     */
    private function sendUpcomingReminders(Carbon $dueDate, string $reminderType, GeneralSetting $settings): int
    {
        $this->info("Checking for payments due on {$dueDate->format('Y-m-d')} ({$reminderType})...");

        $schedules = LoanPaymentSchedule::with(['loan.customer'])
            ->where('due_date', $dueDate->format('Y-m-d'))
            ->whereIn('status', ['upcoming', 'partial'])
            ->where('remaining_amount', '>', 0)
            ->where('is_restructured', false)
            ->get();

        $sentCount = 0;

        foreach ($schedules as $schedule) {
            $customer = $schedule->loan->customer;

            // Check if reminder already sent for this schedule and type
            $alreadySent = DB::table('repayment_reminder_logs')
                ->where('loan_payment_schedule_id', $schedule->id)
                ->where('reminder_type', $reminderType)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            // Skip if customer is inactive
            if ($customer->status !== 'active') {
                continue;
            }

            try {
                $this->sendReminder($customer, $schedule, $reminderType, $settings);
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send reminder to {$customer->full_name}: " . $e->getMessage());
                Log::error('Repayment reminder error', [
                    'customer_id' => $customer->id,
                    'schedule_id' => $schedule->id,
                    'reminder_type' => $reminderType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$sentCount} reminders for {$reminderType}.");
        return $sentCount;
    }

    /**
     * Send reminders for missed payments
     */
    private function sendMissedPaymentReminders(Carbon $today, GeneralSetting $settings): int
    {
        $this->info('Checking for missed payments...');

        $schedules = LoanPaymentSchedule::with(['loan.customer'])
            ->where('due_date', '<', $today->format('Y-m-d'))
            ->whereIn('status', ['overdue', 'partial'])
            ->where('remaining_amount', '>', 0)
            ->where('is_restructured', false)
            ->get();

        $sentCount = 0;

        foreach ($schedules as $schedule) {
            $customer = $schedule->loan->customer;

            // Skip if customer is inactive
            if ($customer->status !== 'active') {
                continue;
            }

            // Count how many missed reminders have been sent
            $missedRemindersSent = DB::table('repayment_reminder_logs')
                ->where('loan_payment_schedule_id', $schedule->id)
                ->whereIn('reminder_type', ['missed_1', 'missed_2'])
                ->count();

            // Determine which reminder to send
            if ($missedRemindersSent >= $settings->missed_payment_reminder_count) {
                continue; // Already sent max reminders
            }

            $reminderType = $missedRemindersSent === 0 ? 'missed_1' : 'missed_2';

            // Check if this specific reminder type was already sent
            $alreadySent = DB::table('repayment_reminder_logs')
                ->where('loan_payment_schedule_id', $schedule->id)
                ->where('reminder_type', $reminderType)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            // For missed payments, send reminder if it's been at least 1 day since due date
            // and for second reminder, wait a few more days
            $daysOverdue = Carbon::parse($schedule->due_date)->diffInDays($today, false);
            
            if ($reminderType === 'missed_1' && $daysOverdue < 1) {
                continue; // Wait at least 1 day after due date
            }

            if ($reminderType === 'missed_2' && $daysOverdue < 3) {
                continue; // Wait at least 3 days after due date for second reminder
            }

            try {
                $this->sendReminder($customer, $schedule, $reminderType, $settings);
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send missed payment reminder to {$customer->full_name}: " . $e->getMessage());
                Log::error('Missed payment reminder error', [
                    'customer_id' => $customer->id,
                    'schedule_id' => $schedule->id,
                    'reminder_type' => $reminderType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$sentCount} missed payment reminders.");
        return $sentCount;
    }

    /**
     * Send a reminder to a customer
     */
    private function sendReminder(Customer $customer, LoanPaymentSchedule $schedule, string $reminderType, GeneralSetting $settings): void
    {
        $dueDate = Carbon::parse($schedule->due_date);
        $amount = number_format($schedule->remaining_amount, 2);
        $loan = $schedule->loan;

        // Build message based on reminder type
        $subject = '';
        $message = '';

        switch ($reminderType) {
            case '1_week_before':
                $subject = 'Payment Reminder - Due in 1 Week';
                $message = "Hello {$customer->first_name},\n\n";
                $message .= "This is a reminder that you have a loan payment due in 1 week.\n\n";
                $message .= "**Payment Details:**\n";
                $message .= "Amount Due: ZMW {$amount}\n";
                $message .= "Due Date: {$dueDate->format('F d, Y')}\n";
                $message .= "Loan Reference: {$loan->loan_number}\n\n";
                $message .= "Please ensure you have sufficient funds available for this payment.\n\n";
                $message .= "Thank you for your prompt attention to this matter.";
                break;

            case '2_days_before':
                $subject = 'Payment Reminder - Due in 2 Days';
                $message = "Hello {$customer->first_name},\n\n";
                $message .= "This is a reminder that you have a loan payment due in 2 days.\n\n";
                $message .= "**Payment Details:**\n";
                $message .= "Amount Due: ZMW {$amount}\n";
                $message .= "Due Date: {$dueDate->format('F d, Y')}\n";
                $message .= "Loan Reference: {$loan->loan_number}\n\n";
                $message .= "Please ensure you have sufficient funds available for this payment.\n\n";
                $message .= "Thank you for your prompt attention to this matter.";
                break;

            case '1_day_before':
                $subject = 'Payment Reminder - Due Tomorrow';
                $message = "Hello {$customer->first_name},\n\n";
                $message .= "This is a reminder that you have a loan payment due tomorrow.\n\n";
                $message .= "**Payment Details:**\n";
                $message .= "Amount Due: ZMW {$amount}\n";
                $message .= "Due Date: {$dueDate->format('F d, Y')}\n";
                $message .= "Loan Reference: {$loan->loan_number}\n\n";
                $message .= "Please ensure you have sufficient funds available for this payment.\n\n";
                $message .= "Thank you for your prompt attention to this matter.";
                break;

            case 'missed_1':
                $daysOverdue = $dueDate->diffInDays(Carbon::today(), false);
                $subject = 'Payment Overdue - Immediate Attention Required';
                $message = "Hello {$customer->first_name},\n\n";
                $message .= "This is a reminder that you have a missed loan payment.\n\n";
                $message .= "**Payment Details:**\n";
                $message .= "Amount Due: ZMW {$amount}\n";
                $message .= "Due Date: {$dueDate->format('F d, Y')}\n";
                $message .= "Days Overdue: {$daysOverdue}\n";
                $message .= "Loan Reference: {$loan->loan_number}\n\n";
                $message .= "Please make this payment as soon as possible to avoid additional charges or penalties.\n\n";
                $message .= "If you have already made this payment, please ignore this message.";
                break;

            case 'missed_2':
                $daysOverdue = $dueDate->diffInDays(Carbon::today(), false);
                $subject = 'Urgent: Payment Overdue - Final Reminder';
                $message = "Hello {$customer->first_name},\n\n";
                $message .= "This is a final reminder that you have a missed loan payment.\n\n";
                $message .= "**Payment Details:**\n";
                $message .= "Amount Due: ZMW {$amount}\n";
                $message .= "Due Date: {$dueDate->format('F d, Y')}\n";
                $message .= "Days Overdue: {$daysOverdue}\n";
                $message .= "Loan Reference: {$loan->loan_number}\n\n";
                $message .= "Please make this payment immediately to avoid further action.\n\n";
                $message .= "If you have already made this payment, please ignore this message.";
                break;
        }

        // Determine communication type (default to both email and SMS)
        $communicationType = 'both';

        // Send email if customer has email
        if ($customer->email) {
            try {
                Mail::raw($message, function ($mail) use ($customer, $subject) {
                    $mail->to($customer->email, $customer->full_name)
                        ->subject($subject);
                });
            } catch (\Exception $e) {
                Log::error('Failed to send repayment reminder email', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send SMS if customer has phone (logged for now, integrate SMS service later)
        if ($customer->phone) {
            Log::info('Repayment Reminder SMS', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'message' => $message,
            ]);
            // TODO: Integrate SMS service
        }

        // Log to communications
        try {
            $communication = CommunicationLogger::log(
                subject: $subject,
                message: $message,
                type: $communicationType,
                isSensitive: false,
                recipient: $customer,
                createdBy: null, // System generated
                metadata: [
                    'notification_type' => 'repayment_reminder',
                    'reminder_type' => $reminderType,
                    'loan_id' => $loan->id,
                    'loan_payment_schedule_id' => $schedule->id,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'amount' => $schedule->remaining_amount,
                ]
            );

            // Log to repayment_reminder_logs
            DB::table('repayment_reminder_logs')->insert([
                'loan_payment_schedule_id' => $schedule->id,
                'customer_id' => $customer->id,
                'reminder_type' => $reminderType,
                'reminder_date' => Carbon::today(),
                'due_date' => $dueDate,
                'amount' => $schedule->remaining_amount,
                'communication_type' => $communicationType,
                'communication_id' => $communication->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log repayment reminder', [
                'customer_id' => $customer->id,
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
