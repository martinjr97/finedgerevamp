<?php

namespace Tests\Unit;

use App\Services\GroupLoanCalculationService;
use InvalidArgumentException;
use Tests\TestCase;

class GroupLoanCalculationServiceTest extends TestCase
{
    public function test_it_calculates_member_and_group_totals(): void
    {
        $service = new GroupLoanCalculationService();

        $result = $service->calculate([
            'processing_fee_percentage' => 5,
            'monthly_interest_rate' => 10,
            'arrears_rate' => 3,
            'repayment_structure' => 'monthly',
            'start_date' => '2026-01-01',
            'due_date' => '2026-03-02',
            'principals' => [
                10 => 1000,
                20 => 500,
            ],
        ]);

        $this->assertCount(2, $result['members']);
        $this->assertSame(2, $result['installment_count']);
        $this->assertEquals(60, $result['duration_days']);

        $this->assertEquals(1500.00, $result['totals']['principal_amount']);
        $this->assertEquals(75.00, $result['totals']['processing_fee_amount']);
        $this->assertEquals(150.00, $result['totals']['interest_amount']);
        $this->assertEquals(1725.00, $result['totals']['repayment_amount']);
        $this->assertEquals(1500.00, $result['totals']['disbursement_amount']);
    }

    public function test_it_applies_interest_rate_once_for_the_full_period_regardless_of_duration(): void
    {
        $service = new GroupLoanCalculationService();

        $shortTerm = $service->calculate([
            'processing_fee_percentage' => 0,
            'monthly_interest_rate' => 20,
            'arrears_rate' => 0,
            'repayment_structure' => 'monthly',
            'start_date' => '2026-01-01',
            'due_date' => '2026-02-01',
            'principals' => [10 => 1000],
        ]);

        $longTerm = $service->calculate([
            'processing_fee_percentage' => 0,
            'monthly_interest_rate' => 20,
            'arrears_rate' => 0,
            'repayment_structure' => 'monthly',
            'start_date' => '2026-01-01',
            'due_date' => '2026-11-01',
            'principals' => [10 => 1000],
        ]);

        $this->assertEquals(200.00, $shortTerm['totals']['interest_amount']);
        $this->assertEquals(200.00, $longTerm['totals']['interest_amount']);
        $this->assertEquals(1200.00, $shortTerm['totals']['repayment_amount']);
        $this->assertEquals(1200.00, $longTerm['totals']['repayment_amount']);
    }

    public function test_it_rejects_invalid_repayment_structure(): void
    {
        $service = new GroupLoanCalculationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Repayment structure must be weekly or monthly.');

        $service->calculate([
            'processing_fee_percentage' => 1,
            'monthly_interest_rate' => 1,
            'arrears_rate' => 1,
            'repayment_structure' => 'daily',
            'start_date' => '2026-01-01',
            'due_date' => '2026-02-01',
            'principals' => [1 => 100],
        ]);
    }

    public function test_it_rejects_due_date_before_or_equal_start_date(): void
    {
        $service = new GroupLoanCalculationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Due date must be after start date.');

        $service->calculate([
            'processing_fee_percentage' => 1,
            'monthly_interest_rate' => 1,
            'arrears_rate' => 1,
            'repayment_structure' => 'weekly',
            'start_date' => '2026-02-01',
            'due_date' => '2026-02-01',
            'principals' => [1 => 100],
        ]);
    }

    public function test_it_rejects_zero_or_negative_principal_amounts(): void
    {
        $service = new GroupLoanCalculationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Principal amount must be greater than zero for all members.');

        $service->calculate([
            'processing_fee_percentage' => 1,
            'monthly_interest_rate' => 1,
            'arrears_rate' => 1,
            'repayment_structure' => 'weekly',
            'start_date' => '2026-01-01',
            'due_date' => '2026-02-01',
            'principals' => [
                1 => 500,
                2 => 0,
            ],
        ]);
    }
}
