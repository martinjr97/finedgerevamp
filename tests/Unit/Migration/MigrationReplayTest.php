<?php

namespace Tests\Unit\Migration;

use App\Migration\LegacyProductMapper;
use App\Migration\RepaymentAttributionService;
use App\Migration\Replay\DTOs\ReplayAllocation;
use App\Migration\Replay\Strategies\CharacterReplayStrategy;
use App\Migration\Replay\Strategies\MarketizeReplayStrategy;
use App\Migration\Replay\Strategies\SalaryBasedClientReplayStrategy;
use App\Migration\Replay\Support\LegacyRepaymentContext;
use Tests\TestCase;

class MigrationReplayTest extends TestCase
{
    private RepaymentAttributionService $attribution;

    private LegacyRepaymentContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attribution = new RepaymentAttributionService(new LegacyProductMapper);
        $this->context = new LegacyRepaymentContext($this->attribution);
    }

    public function test_character_single_loan_replay(): void
    {
        $strategy = new CharacterReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            10 => $this->loanState(10, 1, '2024-01-01', 1000, 0),
        ];
        $repayment = ['id' => 100, 'user_id' => 1, 'repayment_amount' => 400, 'created_at' => '2024-06-01 10:00:00'];

        $result = $strategy->replay($repayment, null, ['product_type' => 'character_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::B_RECONSTRUCTED, $result->classification);
        $this->assertCount(1, $result->allocations);
        $this->assertSame(400.0, $result->allocations[0]->allocatedAmount);
        $this->assertSame(10, $result->allocations[0]->legacyLoanId);
    }

    public function test_character_waterfall_spans_two_loans(): void
    {
        $strategy = new CharacterReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            10 => $this->loanState(10, 1, '2024-01-01', 400, 0, '2024-03-01'),
            11 => $this->loanState(11, 1, '2024-02-01', 600, 0, '2024-04-01'),
        ];
        $repayment = ['id' => 101, 'user_id' => 1, 'repayment_amount' => 1000, 'created_at' => '2024-06-01 10:00:00'];

        $result = $strategy->replay($repayment, null, ['product_type' => 'character_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::B_RECONSTRUCTED, $result->classification);
        $this->assertCount(2, $result->allocations);
        $this->assertSame(400.0, $result->allocations[0]->allocatedAmount);
        $this->assertSame(600.0, $result->allocations[1]->allocatedAmount);
        $this->assertSame(
            1000.0,
            array_sum(array_map(fn (ReplayAllocation $a) => $a->allocatedAmount, $result->allocations))
        );
    }

    public function test_mou_direct_affected_loan_ids(): void
    {
        $strategy = new SalaryBasedClientReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            20 => $this->accrualLoanState(20, 1, '2024-01-01', 5000),
        ];
        $repayment = [
            'id' => 200,
            'user_id' => 1,
            'repayment_amount' => 500,
            'created_at' => '2024-06-01 10:00:00',
            'affected_loan_ids' => json_encode([['loan_id' => 20, 'amount_applied' => 500]]),
        ];

        $result = $strategy->replay($repayment, null, ['product_type' => 'salary_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::A_DIRECT, $result->classification);
        $this->assertSame(500.0, $result->allocations[0]->allocatedAmount);
    }

    public function test_mou_single_eligible_loan_reconstructed(): void
    {
        $strategy = new SalaryBasedClientReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            20 => $this->accrualLoanState(20, 1, '2024-01-01', 5000, salaryBased: 1),
        ];
        $repayment = ['id' => 201, 'user_id' => 1, 'repayment_amount' => 300, 'created_at' => '2024-06-01 10:00:00'];

        $result = $strategy->replay($repayment, null, ['product_type' => 'salary_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::B_RECONSTRUCTED, $result->classification);
        $this->assertSame(4700.0, $loanStates[20]['current_loan_amount']);
    }

    public function test_mou_multi_eligible_loan_ambiguous(): void
    {
        $strategy = new SalaryBasedClientReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            20 => $this->accrualLoanState(20, 1, '2024-01-01', 5000, salaryBased: 1),
            21 => $this->accrualLoanState(21, 1, '2024-02-01', 6000, salaryBased: 0),
        ];
        $repayment = ['id' => 202, 'user_id' => 1, 'repayment_amount' => 300, 'created_at' => '2024-06-01 10:00:00'];

        $result = $strategy->replay($repayment, null, ['product_type' => 'salary_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::C_AMBIGUOUS, $result->classification);
        $this->assertSame([], $result->allocations);
    }

    public function test_marketize_installment_replay(): void
    {
        $strategy = new MarketizeReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            30 => $this->loanState(30, 1, '2024-01-01', 1200, 0),
        ];
        $repayment = ['id' => 300, 'user_id' => 1, 'repayment_amount' => 200, 'created_at' => '2024-06-01 10:00:00'];

        $result = $strategy->replay($repayment, ['is_marketize_customer' => true], ['product_type' => 'marketize_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::B_RECONSTRUCTED, $result->classification);
        $this->assertSame(200.0, $result->allocations[0]->allocatedAmount);
    }

    public function test_invalid_affected_loan_ids_falls_back_from_direct(): void
    {
        $strategy = new SalaryBasedClientReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            20 => $this->accrualLoanState(20, 1, '2024-01-01', 5000, salaryBased: 1),
        ];
        $repayment = [
            'id' => 203,
            'user_id' => 1,
            'repayment_amount' => 500,
            'created_at' => '2024-06-01 10:00:00',
            'affected_loan_ids' => json_encode([['loan_id' => 20, 'amount_applied' => 100]]),
        ];

        $result = $strategy->replay($repayment, null, ['product_type' => 'salary_based'], $loanStates);

        $this->assertNotSame(RepaymentAttributionService::A_DIRECT, $result->classification);
    }

    public function test_cross_customer_affected_loan_rejected(): void
    {
        $strategy = new CharacterReplayStrategy($this->context, $this->attribution);
        $loanStates = [
            10 => $this->loanState(10, 2, '2024-01-01', 1000, 0),
        ];
        $repayment = [
            'id' => 102,
            'user_id' => 1,
            'repayment_amount' => 100,
            'created_at' => '2024-06-01 10:00:00',
            'affected_loan_ids' => json_encode([['loan_id' => 10, 'amount_applied' => 100]]),
        ];

        $result = $strategy->replay($repayment, null, ['product_type' => 'character_based'], $loanStates);

        $this->assertSame(RepaymentAttributionService::D_MANUAL, $result->classification);
    }

    /**
     * @return array<string, mixed>
     */
    private function loanState(int $id, int $userId, string $createdAt, float $loanAmount, float $repaid, ?string $dueDate = null): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'created_at' => $createdAt,
            'loan_amount' => $loanAmount,
            'obtained_amount' => $loanAmount * 0.86,
            'repaid_amount' => $repaid,
            'status' => '301',
            'status_code' => '301',
            'settled_before_payment' => false,
            'due_date' => $dueDate ?? '2024-05-01',
            'salary_based' => 0,
            'gvnt_loan' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accrualLoanState(int $id, int $userId, string $createdAt, float $amount, int $salaryBased = 1): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'created_at' => $createdAt,
            'loan_amount' => $amount,
            'obtained_amount' => $amount * 0.7,
            'current_loan_amount' => $amount,
            'repaid_amount' => 0,
            'status' => '301',
            'status_code' => '301',
            'settled_before_payment' => false,
            'salary_based' => $salaryBased,
            'gvnt_loan' => 0,
        ];
    }
}
