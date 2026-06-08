<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\Ministry;
use App\Models\SecurityQuestion;
use App\Support\NationalIdRules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GovernmentSampleCustomerSeeder extends Seeder
{
    public const SAMPLE_PHONE = '260978200001';

    public const SAMPLE_PIN = '1234';

    public const SAMPLE_EMAIL = 'gov.sample';

    public function run(): void
    {
        $loanProduct = LoanProduct::query()->where('code', 'GOV-001')->first();

        if (! $loanProduct) {
            $this->command?->error('Government product GOV-001 not found. Run LoanProductSeeder first.');

            return;
        }

        $customerGroup = CustomerGroup::query()
            ->where('loan_product_id', $loanProduct->id)
            ->where('code', 'GOV-DEFAULT')
            ->first();

        if (! $customerGroup) {
            $this->command?->error('GOV-DEFAULT customer group not found. Run LoanProductSeeder first.');

            return;
        }

        $ministry = Ministry::query()->where('code', 'MOF')->first();

        if (! $ministry) {
            $this->command?->error('Ministry MOF not found. Run MinistrySeeder first.');

            return;
        }

        $verifiedBy = Admin::query()
            ->where('is_active', true)
            ->where('is_relationship_manager', true)
            ->orderBy('id')
            ->first();

        if (! $verifiedBy) {
            $this->command?->error('No active relationship manager found. Run SuperAdminSeeder first.');

            return;
        }

        $securityQuestion = SecurityQuestion::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $domain = config('app.email_domain');
        $email = self::SAMPLE_EMAIL.'@'.$domain;
        $netSalary = 12000.00;

        $customer = Customer::query()->updateOrCreate(
            ['phone' => self::SAMPLE_PHONE],
            [
                'loan_product_id' => $loanProduct->id,
                'customer_group_id' => $customerGroup->id,
                'first_name' => 'Grace',
                'last_name' => 'Banda',
                'email' => $email,
                'password' => Hash::make(self::SAMPLE_PIN),
                'national_id_type' => NationalIdRules::TYPE_NRC,
                'national_id' => '555555/66/7',
                'tpin' => '99887766',
                'date_of_birth' => '1990-05-15',
                'gender' => 'female',
                'address_line1' => 'Plot 12, Independence Avenue',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_by' => $verifiedBy->id,
                'approved_at' => now(),
                'employment_status' => 'employed',
                'ministry_id' => $ministry->id,
                'employee_number' => 'GOV-SAMPLE-001',
                'date_of_employment' => '2018-01-15',
                'contract_end_date' => null,
                'gross_salary' => 15000.00,
                'net_salary' => $netSalary,
                'deductions' => 3000.00,
                'verified_by' => $verifiedBy->id,
                'next_of_kin_name' => 'Peter Banda',
                'next_of_kin_phone' => '260978200002',
                'next_of_kin_relationship' => 'spouse',
                'next_of_kin_city' => 'Lusaka',
                'next_of_kin_country' => 'Zambia',
                'maximum_loan_take' => $netSalary * 0.6,
                'must_change_pin' => false,
                'must_change_password' => false,
                'security_question_id' => $securityQuestion?->id,
                'security_answer' => $securityQuestion ? 'Lusaka' : null,
            ]
        );

        $this->command?->info('Government sample customer ready.');
        $this->command?->line('  Name: '.$customer->first_name.' '.$customer->last_name);
        $this->command?->line('  Phone: '.self::SAMPLE_PHONE);
        $this->command?->line('  PIN: '.self::SAMPLE_PIN);
        $this->command?->line('  Email: '.$email);
        $this->command?->line('  Product: '.$loanProduct->name.' ('.$loanProduct->code.')');
        $this->command?->line('  Login: '.url('/customer/login'));
    }
}
