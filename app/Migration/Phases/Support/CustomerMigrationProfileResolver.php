<?php

namespace App\Migration\Phases\Support;

use App\Migration\LegacyProductMapper;
use App\Migration\Phases\ProductCustomerGroupReferenceMigrator;

class CustomerMigrationProfileResolver
{
    public function __construct(
        private readonly LegacyProductMapper $productMapper,
        private readonly LegacyClientClassifier $clientClassifier,
        private readonly MarketeerClassifier $marketeerClassifier,
        private readonly ProductCustomerGroupReferenceMigrator $customerGroupMigrator,
    ) {}

    /**
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>|null  $legacyClient
     * @param  \Illuminate\Support\Collection<int, object>  $clientLoans
     * @return array{
     *     product_code: string,
     *     product_reason: string,
     *     client_classification: string,
     *     company_id: int|null,
     *     customer_group_id: int|null,
     *     requires_market_detail: bool
     * }
     */
    public function resolve(
        array $legacyCustomer,
        ?array $legacyClient,
        $clientLoans,
        ?int $mouCompanyId,
    ): array {
        $clientId = (int) ($legacyCustomer['client_id'] ?? 0);
        $customerCount = 1;
        $classification = $legacyClient
            ? $this->clientClassifier->classify($legacyClient, $clientLoans, $customerCount)
            : LegacyClientClassifier::AMBIGUOUS_MANUAL_REVIEW;

        $isMarketeer = $this->marketeerClassifier->isMarketeerCustomer($legacyCustomer, $legacyClient);

        $productMap = match (true) {
            $classification === LegacyClientClassifier::GOVERNMENT_PRODUCT_PLACEHOLDER => [
                'code' => 'GOV-001',
                'reason' => 'government_placeholder_client',
            ],
            $classification === LegacyClientClassifier::CHARACTER_PRODUCT_PLACEHOLDER => [
                'code' => 'CHAR-001',
                'reason' => 'character_placeholder_client',
            ],
            $isMarketeer || $classification === LegacyClientClassifier::MARKETEER_PRODUCT_PLACEHOLDER => [
                'code' => 'MARK-001',
                'reason' => 'marketeer_customer',
            ],
            $this->clientClassifier->shouldCreateOrMatchCompany($classification) => array_merge(
                $this->productMapper->mapLoanProduct(['salary_based' => 1, 'gvnt_loan' => 0], $legacyClient),
                ['reason' => 'mou_employer_client']
            ),
            default => $this->productMapper->mapLoanProduct(['salary_based' => 0, 'gvnt_loan' => 0], $legacyClient),
        };

        $companyId = null;
        if ($this->clientClassifier->shouldCreateOrMatchCompany($classification) && ! $isMarketeer) {
            $companyId = $mouCompanyId;
        }

        $customerGroupId = null;
        if ($classification === LegacyClientClassifier::GOVERNMENT_PRODUCT_PLACEHOLDER) {
            $customerGroupId = $this->customerGroupMigrator->governmentDefaultGroupId();
        } elseif ($classification === LegacyClientClassifier::CHARACTER_PRODUCT_PLACEHOLDER && $clientId) {
            $customerGroupId = $this->customerGroupMigrator->characterGroupIdForLegacyClient($clientId);
        } elseif ($isMarketeer || $classification === LegacyClientClassifier::MARKETEER_PRODUCT_PLACEHOLDER) {
            $customerGroupId = $this->customerGroupMigrator->marketeerGroupId();
        }

        return [
            'product_code' => $productMap['code'],
            'product_reason' => $productMap['reason'] ?? 'resolved',
            'client_classification' => $classification,
            'company_id' => $companyId,
            'customer_group_id' => $customerGroupId,
            'requires_market_detail' => $isMarketeer,
        ];
    }
}
