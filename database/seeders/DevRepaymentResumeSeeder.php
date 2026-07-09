<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Additive dev fixture only — never deletes or truncates existing records.
 * Re-run safely to ensure a customer + active loan exist for repayment testing.
 */
class DevRepaymentResumeSeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'dev.repayment@finedge.co.zm';

    public const CUSTOMER_PHONE = '260978232334';

    public const CUSTOMER_PIN = '1234';

    public const LOAN_NUMBER = 'DEV-LOAN-001';

    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            LoanProductSeeder::class,
            ChannelSeeder::class,
            CashRegisterSeeder::class,
            CGratePaymentGatewaySeeder::class,
            PaymentGatewayRouteSeeder::class,
        ]);

        $company = Company::query()->where('slug', 'main-operator')->firstOrFail();
        $loanProduct = LoanProduct::query()->where('code', 'CHAR-001')->firstOrFail();
        $channel = Channel::query()->where('code', 'MTN_MONEY')->firstOrFail();

        $customer = Customer::query()->updateOrCreate(
            ['phone' => self::CUSTOMER_PHONE],
            [
                'company_id' => $company->id,
                'loan_product_id' => $loanProduct->id,
                'first_name' => 'Dev',
                'last_name' => 'Repayment',
                'email' => self::CUSTOMER_EMAIL,
                'password' => Hash::make(self::CUSTOMER_PIN),
                'status' => 'active',
                'approval_status' => 'approved',
                'must_change_pin' => false,
                'must_change_password' => false,
            ]
        );

        $principal = 5000.00;

        $loan = Loan::query()->updateOrCreate(
            ['loan_number' => self::LOAN_NUMBER],
            [
                'customer_id' => $customer->id,
                'loan_product_id' => $loanProduct->id,
                'channel_id' => $channel->id,
                'principal_amount' => $principal,
                'processing_fee' => 0,
                'interest_accrued' => 0,
                'total_amount' => $principal,
                'amount_paid' => 0,
                'outstanding_balance' => $principal,
                'tenure_months' => 3,
                'loan_start_date' => now()->subMonth()->toDateString(),
                'loan_end_date' => now()->addMonths(2)->toDateString(),
                'first_payment_date' => now()->subDays(5)->toDateString(),
                'last_payment_date' => now()->addMonths(2)->toDateString(),
                'accrual_type' => 'daily',
                'status' => 'active',
                'disbursement_status' => 'completed',
                'disbursed_at' => now()->subMonth(),
            ]
        );

        LoanPaymentSchedule::query()->updateOrCreate(
            [
                'loan_id' => $loan->id,
                'period_number' => 1,
            ],
            [
                'due_date' => now()->addDays(15)->toDateString(),
                'expected_amount' => $principal,
                'amount_paid' => 0,
                'remaining_amount' => $principal,
                'status' => 'upcoming',
                'days_overdue' => 0,
            ]
        );

        $wallet = Wallet::query()->firstOrCreate(
            ['wallet_number' => '260955000001'],
            [
                'name' => 'cGrate Dev Wallet',
                'provider' => 'other',
                'currency' => 'ZMW',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ]
        );

        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();
        $gateway->update([
            'status' => PaymentGatewayStatus::Active,
            'financial_account_type' => FinancialAccountType::Wallet,
            'financial_account_id' => $wallet->id,
        ]);

        PaymentGatewayRoute::query()
            ->where('route_key', GatewayRouteKey::WalletCollection->value)
            ->update([
                'payment_gateway_id' => $gateway->id,
                'enabled' => true,
                'fallback_to_manual' => true,
            ]);

        $this->command?->newLine();
        $this->command?->info('Dev repayment fixture ready (additive — nothing was deleted).');
        $this->command?->table(
            ['Item', 'Value'],
            [
                ['Super admin email', 'superadmin@'.config('app.email_domain')],
                ['Super admin password', 'ChangeMe123!'],
                ['Customer', $customer->first_name.' '.$customer->last_name],
                ['Customer email', self::CUSTOMER_EMAIL],
                ['Customer phone', self::CUSTOMER_PHONE],
                ['Customer PIN', self::CUSTOMER_PIN],
                ['Loan number', $loan->loan_number],
                ['Loan ID', (string) $loan->id],
                ['Outstanding', 'ZMW '.number_format((float) $loan->outstanding_balance, 2)],
                ['Admin repayment URL', url('/admin/customers/'.$customer->id.'/repayments/create?loan_id='.$loan->id)],
            ]
        );
    }
}
