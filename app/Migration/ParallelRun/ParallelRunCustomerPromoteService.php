<?php

namespace App\Migration\ParallelRun;

use App\Migration\Phases\CustomerMigrator;
use App\Migration\Phases\MigrationEntityMapRepository;
class ParallelRunCustomerPromoteService
{
    public function __construct(
        private readonly CustomerMigrator $customerMigrator,
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * @return array{success: bool, status: string, message: string, target_customer_id: ?int, summary: ?array<string, mixed>}
     */
    public function promoteLegacyUser(int $legacyUserId): array
    {
        $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $legacyUserId);
        if ($existingMap) {
            return [
                'success' => true,
                'status' => 'already_mapped',
                'message' => 'Customer is already mapped.',
                'target_customer_id' => (int) $existingMap->target_id,
                'summary' => null,
            ];
        }

        try {
            $summary = $this->customerMigrator->run(
                promote: true,
                legacyUserId: $legacyUserId,
            );
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'blocked',
                'message' => $e->getMessage(),
                'target_customer_id' => null,
                'summary' => null,
            ];
        }

        $map = $this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $legacyUserId);
        if ($map) {
            return [
                'success' => true,
                'status' => ($summary['created'] ?? 0) > 0 ? 'created' : 'matched_existing',
                'message' => 'Customer promoted successfully.',
                'target_customer_id' => (int) $map->target_id,
                'summary' => $summary,
            ];
        }

        if (($summary['manual_review'] ?? 0) > 0) {
            return [
                'success' => false,
                'status' => 'manual_review',
                'message' => 'Customer requires manual review in migration staging — map on the customer detail page or resolve identity issues.',
                'target_customer_id' => null,
                'summary' => $summary,
            ];
        }

        if (($summary['identity_excluded'] ?? 0) > 0) {
            return [
                'success' => false,
                'status' => 'excluded',
                'message' => 'Legacy user is excluded from migration by identity resolution rules.',
                'target_customer_id' => null,
                'summary' => $summary,
            ];
        }

        return [
            'success' => false,
            'status' => 'blocked',
            'message' => 'Customer promotion did not create or match a target customer.',
            'target_customer_id' => null,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{success: bool, status: string, message: string, target_customer_id: ?int, summary: ?array<string, mixed>}
     */
    public function ensureMapped(int $legacyUserId): array
    {
        return $this->promoteLegacyUser($legacyUserId);
    }
}
