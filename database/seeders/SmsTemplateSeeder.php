<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use App\Sms\Enums\SmsCategory;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $maxLength = (int) config('sms.max_length', 159);

        $templates = [
            [
                'key' => 'customer_approved',
                'name' => 'Customer account approved',
                'category' => SmsCategory::Security,
                'body' => 'Hi {NAME}, your {APP_NAME} account is approved. Login: {PHONE} PIN: {PIN}. Change PIN on first login.',
                'description' => 'Placeholders: {NAME}, {PHONE}, {PIN}, {APP_NAME}',
            ],
            [
                'key' => 'pin_reset_admin',
                'name' => 'Admin PIN reset',
                'category' => SmsCategory::Security,
                'body' => 'Hi {NAME}, your {APP_NAME} PIN was reset. Login: {PHONE} PIN: {PIN}. Change PIN on first login.',
                'description' => 'Placeholders: {NAME}, {PHONE}, {PIN}, {APP_NAME}',
            ],
            [
                'key' => 'repayment_success_full',
                'name' => 'Repayment success (settled)',
                'category' => SmsCategory::Payment,
                'body' => 'Hi {NAME}, repayment of K{AMOUNT} received. Your loan balance is now settled. Thank you — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {AMOUNT}, {APP_NAME}',
            ],
            [
                'key' => 'repayment_success_partial',
                'name' => 'Repayment success (partial)',
                'category' => SmsCategory::Payment,
                'body' => 'Hi {NAME}, repayment of K{AMOUNT} received. Outstanding balance: K{BALANCE}. Thank you — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {AMOUNT}, {BALANCE}, {APP_NAME}',
            ],
            [
                'key' => 'repayment_failed',
                'name' => 'Repayment failed',
                'category' => SmsCategory::Payment,
                'body' => 'Hi {NAME}, repayment of K{AMOUNT} was not successful. Outstanding: K{BALANCE}. Contact us if you need help — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {AMOUNT}, {BALANCE}, {REPAYMENT_NUMBER}, {APP_NAME}',
            ],
            [
                'key' => 'loan_disbursed',
                'name' => 'Loan disbursed',
                'category' => SmsCategory::Loan,
                'body' => 'Hi {NAME}, loan {LOAN_NUMBER} of K{AMOUNT} disbursed. Next payment: {DUE_DATE}. Ref: {REFERENCE} — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {LOAN_NUMBER}, {AMOUNT}, {DUE_DATE}, {REFERENCE}, {APP_NAME}',
            ],
            [
                'key' => 'reminder_1_week_before',
                'name' => 'Payment reminder (1 week before)',
                'category' => SmsCategory::Loan,
                'body' => 'Hi {NAME}, loan {LOAN_NUMBER} payment of K{AMOUNT} due {DUE_DATE}. Repay early on {APP_NAME} if you can.',
                'description' => 'Placeholders: {NAME}, {LOAN_NUMBER}, {AMOUNT}, {DUE_DATE}, {APP_NAME}',
            ],
            [
                'key' => 'reminder_2_days_before',
                'name' => 'Payment reminder (2 days before)',
                'category' => SmsCategory::Loan,
                'body' => 'Hi {NAME}, loan {LOAN_NUMBER} payment of K{AMOUNT} due {DUE_DATE}. Please ensure funds are ready — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {LOAN_NUMBER}, {AMOUNT}, {DUE_DATE}, {APP_NAME}',
            ],
            [
                'key' => 'reminder_1_day_before',
                'name' => 'Payment reminder (1 day before)',
                'category' => SmsCategory::Loan,
                'body' => 'Hi {NAME}, loan {LOAN_NUMBER} payment of K{AMOUNT} is due tomorrow ({DUE_DATE}). — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {LOAN_NUMBER}, {AMOUNT}, {DUE_DATE}, {APP_NAME}',
            ],
            [
                'key' => 'reminder_missed_1',
                'name' => 'Missed payment reminder',
                'category' => SmsCategory::Loan,
                'body' => 'Hi {NAME}, loan {LOAN_NUMBER} payment of K{AMOUNT} is overdue ({DAYS_OVERDUE} days). Please pay soon — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {LOAN_NUMBER}, {AMOUNT}, {DUE_DATE}, {DAYS_OVERDUE}, {APP_NAME}',
            ],
            [
                'key' => 'reminder_missed_2',
                'name' => 'Final missed payment reminder',
                'category' => SmsCategory::Loan,
                'body' => 'Hi {NAME}, loan {LOAN_NUMBER} is still overdue. K{AMOUNT} due since {DUE_DATE}. Please pay urgently — {APP_NAME}',
                'description' => 'Placeholders: {NAME}, {LOAN_NUMBER}, {AMOUNT}, {DUE_DATE}, {DAYS_OVERDUE}, {APP_NAME}',
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                array_merge($template, ['max_length' => $maxLength, 'is_active' => true]),
            );
        }
    }
}
