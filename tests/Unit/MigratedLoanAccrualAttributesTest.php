<?php

namespace Tests\Unit;

use App\Migration\Phases\Support\MigratedLoanAccrualAttributes;
use App\Models\Loan;
use App\Models\LoanProduct;
use Tests\TestCase;

class MigratedLoanAccrualAttributesTest extends TestCase
{
    public function test_salary_based_legacy_loan_maps_to_daily_accrual(): void
    {
        $product = new LoanProduct(['accrual_type' => 'at_beginning']);

        $fields = MigratedLoanAccrualAttributes::resolve(['salary_based' => true], $product);

        $this->assertSame('daily', $fields['accrual_type']);
        $this->assertSame(Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL, $fields['interest_behavior']);
    }

    public function test_character_product_daily_maps_to_daily_accrual(): void
    {
        $product = new LoanProduct(['accrual_type' => 'daily']);

        $fields = MigratedLoanAccrualAttributes::resolve(['salary_based' => false], $product);

        $this->assertSame('daily', $fields['accrual_type']);
    }

    public function test_fixed_product_maps_to_upfront_flat(): void
    {
        $product = new LoanProduct(['accrual_type' => 'at_beginning']);

        $fields = MigratedLoanAccrualAttributes::resolve(['salary_based' => false, 'gvnt_loan' => false], $product);

        $this->assertSame('at_beginning', $fields['accrual_type']);
        $this->assertSame(Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT, $fields['interest_behavior']);
    }
}
