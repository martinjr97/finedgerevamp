<?php

namespace Tests\Unit;

use App\Migration\ParallelRun\PendingLoanImportPreviewService;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PendingLoanImportPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_matching_preview_for_character_loan(): void
    {
        $company = Company::create([
            'name' => 'Preview Co',
            'slug' => 'preview-co',
            'code' => 'PC'.Str::upper(Str::random(4)),
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character Loan',
            'code' => 'CHAR-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Jane',
            'last_name' => 'Borrower',
            'full_name' => 'Jane Borrower',
            'email' => 'jane-'.Str::random(5).'@example.com',
            'phone' => '260971234567',
            'password' => '1234',
            'status' => 'active',
        ]);

        DB::table('migration_entity_maps')->insert([
            'entity_type' => MigrationEntityMapRepository::TYPE_CUSTOMER,
            'legacy_identifier' => '88001',
            'target_type' => Customer::class,
            'target_id' => $customer->id,
            'mapping_method' => 'test',
            'mapping_confidence' => 'HIGH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inbox = (object) [
            'legacy_loan_id' => 99001,
            'legacy_user_id' => 88001,
            'status' => 'pending_review',
            'detected_at' => now()->toDateTimeString(),
            'block_reason' => null,
        ];

        $legacyLoan = [
            'id' => 99001,
            'user_id' => 88001,
            'status_code' => '301',
            'obtained_amount' => 5000,
            'loan_amount' => 6390,
            'repaid_amount' => 0,
            'payment_period' => 1,
            'created_at' => '2026-10-01 09:15:00',
            'due_date' => '2026-11-01',
            'first_repayment_date' => '2026-11-01',
            'salary_based' => 0,
            'gvnt_loan' => 0,
        ];

        $preview = app(PendingLoanImportPreviewService::class)->build(
            99001,
            $inbox,
            $legacyLoan,
            [],
        );

        $this->assertSame('LEG-99001', $preview['revamp']['loan_number']);
        $this->assertSame($customer->id, $preview['revamp']['customer_id']);
        $this->assertEqualsWithDelta(5000.0, $preview['revamp']['principal_amount'], 0.01);
        $this->assertEqualsWithDelta(6390.0, $preview['revamp']['outstanding_balance'], 0.01);
        $this->assertTrue($preview['readiness']['can_import']);

        $outstandingRow = collect($preview['comparisons'])->firstWhere('label', 'Outstanding balance');
        $this->assertNotNull($outstandingRow);
        $this->assertSame('match', $outstandingRow['status']);
    }

    public function test_blocks_import_when_customer_not_mapped(): void
    {
        LoanProduct::create([
            'company_id' => Company::create([
                'name' => 'Preview Co 2',
                'slug' => 'preview-co-2',
                'code' => 'PC2'.Str::upper(Str::random(3)),
                'type' => 'partner',
                'status' => 'active',
                'approval_status' => 'approved',
            ])->id,
            'name' => 'Character Loan',
            'code' => 'CHAR-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $inbox = (object) [
            'legacy_loan_id' => 99002,
            'legacy_user_id' => 88002,
            'status' => 'pending_review',
            'detected_at' => now()->toDateTimeString(),
            'block_reason' => null,
        ];

        $legacyLoan = [
            'id' => 99002,
            'user_id' => 88002,
            'status_code' => '301',
            'obtained_amount' => 1000,
            'loan_amount' => 1278,
            'repaid_amount' => 0,
            'payment_period' => 1,
            'created_at' => '2026-10-01 09:15:00',
            'due_date' => '2026-11-01',
        ];

        $preview = app(PendingLoanImportPreviewService::class)->build(99002, $inbox, $legacyLoan, []);

        $this->assertFalse($preview['readiness']['can_import']);
        $this->assertNotEmpty($preview['readiness']['blockers']);
    }
}
