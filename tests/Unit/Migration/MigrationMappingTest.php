<?php

namespace Tests\Unit\Migration;

use App\Migration\LegacyProductMapper;
use App\Migration\PhoneNormalizer;
use App\Migration\RepaymentAttributionService;
use Tests\TestCase;

class MigrationMappingTest extends TestCase
{
    public function test_product_mapper_maps_government_loans(): void
    {
        $mapper = new LegacyProductMapper;
        $result = $mapper->mapLoanProduct(['gvnt_loan' => 1, 'salary_based' => 0], ['product_type' => 'salary_based']);

        $this->assertSame('GOV-001', $result['code']);
        $this->assertSame('government', $result['category']);
    }

    public function test_product_mapper_maps_marketeer_loans(): void
    {
        $mapper = new LegacyProductMapper;
        $result = $mapper->mapLoanProduct(['gvnt_loan' => 0, 'salary_based' => 0], ['product_type' => 'marketize_based']);

        $this->assertSame('MARK-001', $result['code']);
    }

    public function test_phone_normalizer_formats_zambian_numbers(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('260971234567', $normalizer->normalize('0971234567'));
        $this->assertSame('AIRTEL_MONEY', $normalizer->inferProvider('260971234567'));
    }

    public function test_repayment_attribution_marks_multi_mou_as_ambiguous(): void
    {
        $service = new RepaymentAttributionService(new LegacyProductMapper);
        $repayment = ['status_code' => 215, 'affected_loan_ids' => null];
        $activeLoans = [
            ['id' => 1, 'salary_based' => 1, 'gvnt_loan' => 0],
            ['id' => 2, 'salary_based' => 1, 'gvnt_loan' => 0],
        ];
        $client = ['product_type' => 'salary_based'];

        $result = $service->classify($repayment, $activeLoans, $client);

        $this->assertSame(RepaymentAttributionService::C_AMBIGUOUS, $result['class']);
    }

    public function test_repayment_attribution_uses_affected_loan_ids_when_present(): void
    {
        $service = new RepaymentAttributionService(new LegacyProductMapper);
        $repayment = [
            'status_code' => 215,
            'affected_loan_ids' => json_encode([['loan_id' => 42, 'amount_applied' => 100]]),
        ];

        $result = $service->classify($repayment, [], ['product_type' => 'salary_based']);

        $this->assertSame(RepaymentAttributionService::A_DIRECT, $result['class']);
    }
}
