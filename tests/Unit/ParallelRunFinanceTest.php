<?php

namespace Tests\Unit;

use App\Migration\ParallelRun\ParallelRunRepaymentFinanceService;
use App\Migration\ParallelRun\ParallelRunTreasuryResolver;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\Wallet;
use App\Services\Repayments\RepaymentFinancePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParallelRunFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repayment_finance_credits_mapped_wallet(): void
    {
        config(['legacy-parallel-run.finance_on_import_enabled' => true]);

        $company = Company::create([
            'name' => 'Finance Co',
            'slug' => 'finance-co',
            'code' => 'FC'.Str::upper(Str::random(3)),
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character',
            'code' => 'CHAR-'.Str::upper(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Repay',
            'last_name' => 'Test',
            'email' => 'repay-'.Str::random(5).'@example.com',
            'phone' => '260977'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $wallet = Wallet::create([
            'name' => 'Kazang Test',
            'wallet_number' => 'WAL-'.Str::upper(Str::random(6)),
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'repayment_number' => 'R-TEST-001',
            'external_reference' => 'LEG-R-555001',
            'total_amount' => 250,
            'recovery_method' => 'normal',
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $resolver = $this->createMock(ParallelRunTreasuryResolver::class);
        $resolver->method('resolvePaymentSource')->willReturn(['wallet', $wallet->id]);

        $service = new ParallelRunRepaymentFinanceService(
            $resolver,
            app(RepaymentFinancePostingService::class),
        );

        $result = $service->postCollectionFromLegacy($repayment, [
            'id' => 555001,
            'wallet_id' => 99,
            'repayment_amount' => 250,
        ]);

        $this->assertSame('posted', $result['status']);
        $wallet->refresh();
        $this->assertEqualsWithDelta(1250.0, (float) $wallet->current_balance, 0.01);

        $repayment->refresh();
        $this->assertSame('wallet', $repayment->received_via_type);
        $this->assertSame($wallet->id, (int) $repayment->received_via_id);
    }

    public function test_repayment_finance_is_idempotent(): void
    {
        config(['legacy-parallel-run.finance_on_import_enabled' => true]);

        $company = Company::create([
            'name' => 'Finance Co 2',
            'slug' => 'finance-co-2',
            'code' => 'FC2'.Str::upper(Str::random(3)),
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character',
            'code' => 'CHAR-'.Str::upper(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Repay',
            'last_name' => 'Two',
            'email' => 'repay2-'.Str::random(5).'@example.com',
            'phone' => '260978'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $bank = Bank::create([
            'name' => 'Test Bank',
            'account_number' => 'ACC-'.Str::upper(Str::random(6)),
            'account_name' => 'Test Account',
            'bank_name' => 'Test Bank',
            'current_balance' => 500,
            'is_active' => true,
        ]);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'repayment_number' => 'R-TEST-002',
            'external_reference' => 'LEG-R-555002',
            'total_amount' => 100,
            'recovery_method' => 'normal',
            'status' => 'completed',
            'processed_at' => now(),
            'received_via_type' => 'bank',
            'received_via_id' => $bank->id,
            'metadata' => ['finance_posted_at' => now()->toIso8601String()],
        ]);

        $resolver = $this->createMock(ParallelRunTreasuryResolver::class);
        $resolver->expects($this->never())->method('resolvePaymentSource');

        $service = new ParallelRunRepaymentFinanceService(
            $resolver,
            app(RepaymentFinancePostingService::class),
        );

        $result = $service->postCollectionFromLegacy($repayment, ['id' => 555002, 'bank_id' => 1]);
        $this->assertSame('already_posted', $result['status']);

        $bank->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $bank->current_balance, 0.01);
    }
}
