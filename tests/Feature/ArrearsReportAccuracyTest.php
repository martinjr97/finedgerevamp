<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ArrearsReportAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(?Company $company = null): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company ??= Company::create([
            'name' => 'Arrears Accuracy Co '.$suffix,
            'slug' => 'arrears-accuracy-'.$suffix,
            'code' => 'AA'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Arrears',
            'last_name' => 'Admin',
            'email' => 'arrears-accuracy-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('reports.view');

        return $admin;
    }

    private function makeLoanContext(?Company $company = null): array
    {
        $suffix = Str::lower(Str::random(6));
        $company ??= Company::create([
            'name' => 'Borrower Co '.$suffix,
            'slug' => 'borrower-co-'.$suffix,
            'code' => 'BC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character',
            'code' => 'CHR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Borrower',
            'last_name' => 'Test',
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        return compact('company', 'product', 'channel', 'customer', 'suffix');
    }

    public function test_arrears_total_uses_past_due_installments_not_full_loan_amount(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        ['company' => $company, 'product' => $product, 'channel' => $channel, 'customer' => $customer, 'suffix' => $suffix] = $this->makeLoanContext();
        $admin = $this->makeAdmin($company);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'AR-ACC-'.$suffix,
            'principal_amount' => 12000,
            'processing_fee' => 0,
            'total_amount' => 12000,
            'amount_paid' => 11000,
            'outstanding_balance' => 1000,
            'tenure_months' => 12,
            'loan_start_date' => now()->subYear()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subMonths(11)->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subYear(),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 11,
            'due_date' => now()->subDays(5)->toDateString(),
            'expected_amount' => 250,
            'amount_paid' => 0,
            'remaining_amount' => 250,
            'status' => 'overdue',
            'days_overdue' => 5,
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 12,
            'due_date' => now()->addDays(20)->toDateString(),
            'expected_amount' => 750,
            'amount_paid' => 0,
            'remaining_amount' => 750,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.arrears'))
            ->assertOk()
            ->assertViewHas('arrearsSummary', function (array $summary) {
                return $summary['total_loans'] === 1
                    && $summary['total_overdue_amount'] === 250.0
                    && $summary['total_overdue_installments'] === 1;
            });

        Carbon::setTestNow();
    }

    public function test_installments_due_today_are_not_counted_as_overdue(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        ['company' => $company, 'product' => $product, 'channel' => $channel, 'customer' => $customer, 'suffix' => $suffix] = $this->makeLoanContext();
        $admin = $this->makeAdmin($company);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'AR-TODAY-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => now()->toDateString(),
            'expected_amount' => 500,
            'amount_paid' => 0,
            'remaining_amount' => 500,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.arrears'))
            ->assertOk()
            ->assertViewHas('arrearsSummary', fn (array $summary) => $summary['total_loans'] === 0 && $summary['total_overdue_amount'] === 0.0);

        Carbon::setTestNow();
    }

    public function test_overdue_amount_is_capped_at_booked_outstanding_when_schedules_are_stale(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        ['company' => $company, 'product' => $product, 'channel' => $channel, 'customer' => $customer, 'suffix' => $suffix] = $this->makeLoanContext();
        $admin = $this->makeAdmin($company);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'AR-CAP-'.$suffix,
            'principal_amount' => 10000,
            'processing_fee' => 0,
            'total_amount' => 10000,
            'amount_paid' => 3412,
            'outstanding_balance' => 6588,
            'tenure_months' => 3,
            'loan_start_date' => now()->subMonths(4)->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subMonths(3)->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(4),
        ]);

        foreach ([3000.0, 3000.0, 2389.0] as $index => $remaining) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $index + 1,
                'due_date' => now()->subMonths(3 - $index)->toDateString(),
                'expected_amount' => $remaining,
                'amount_paid' => 0,
                'remaining_amount' => $remaining,
                'status' => 'overdue',
                'days_overdue' => 30,
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.arrears'))
            ->assertOk()
            ->assertViewHas('arrearsSummary', function (array $summary) {
                return $summary['total_loans'] === 1
                    && $summary['total_overdue_amount'] === 6588.0
                    && $summary['total_overdue_amount'] <= $summary['total_booked_outstanding'];
            });

        Carbon::setTestNow();
    }
}
