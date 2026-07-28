<?php

namespace Tests\Feature\Sms;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GeneralSetting;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\SmsMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\SeedsSmsTemplates;
use Tests\TestCase;

class RepaymentReminderSmsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSmsTemplates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSmsTemplates();
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
        Mail::fake();
    }

    public function test_reminder_command_queues_short_sms_template(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        GeneralSetting::create([
            'repayment_reminders_enabled' => true,
            'remind_1_week_before' => true,
            'remind_2_days_before' => false,
            'remind_1_day_before' => false,
            'missed_payment_reminder_count' => 0,
        ]);

        $schedule = $this->makeScheduleDueOn(Carbon::parse('2026-07-08'));

        $this->artisan('repayments:send-reminders')->assertSuccessful();

        $this->assertDatabaseHas('sms_messages', [
            'customer_id' => $schedule->loan->customer_id,
            'message_type' => 'repayment_reminder_1_week_before',
            'message_category' => 'loan',
        ]);

        $sms = SmsMessage::query()->first();
        $this->assertNotNull($sms);
        $this->assertLessThanOrEqual(159, mb_strlen($sms->message_body));
        $this->assertStringContainsString($schedule->loan->loan_number, $sms->message_body);
        $this->assertStringNotContainsString('**Payment Details:**', $sms->message_body);
    }

    private function makeScheduleDueOn(Carbon $dueDate): LoanPaymentSchedule
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Reminder SMS Co '.$suffix,
            'slug' => 'reminder-'.$suffix,
            'code' => 'RM'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Reminder Product',
            'code' => 'RMP'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Reminder',
            'last_name' => 'Sms',
            'email' => 'reminder-'.$suffix.'@example.com',
            'phone' => '0977000007',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $channel = Channel::create([
            'name' => 'Reminder Channel '.$suffix,
            'code' => 'RC'.$suffix,
            'type' => 'mobile_wallet',
            'is_active' => true,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-REM-'.$suffix,
            'principal_amount' => 500,
            'processing_fee' => 0,
            'total_amount' => 500,
            'amount_paid' => 0,
            'outstanding_balance' => 500,
            'tenure_months' => 1,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => $dueDate->toDateString(),
            'last_payment_date' => $dueDate->copy()->addDays(30)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        return LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => $dueDate->toDateString(),
            'expected_amount' => 500,
            'amount_paid' => 0,
            'remaining_amount' => 500,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);
    }
}
