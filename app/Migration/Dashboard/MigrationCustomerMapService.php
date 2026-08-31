<?php

namespace App\Migration\Dashboard;

use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MigrationCustomerMapService
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * Map a legacy user to an existing revamp customer (dashboard manual merge).
     *
     * @param  array<string, mixed>  $fieldUpdates
     * @return array{message: string, target_customer_id: int, fields_updated: list<string>}
     */
    public function mapToExistingCustomer(
        int $legacyUserId,
        int $targetCustomerId,
        int $adminId,
        ?string $reason = null,
        array $fieldUpdates = [],
    ): array {
        if ($legacyUserId <= 0) {
            throw ValidationException::withMessages(['legacy_user_id' => 'Invalid legacy user.']);
        }

        $customer = Customer::query()->find($targetCustomerId);
        if (! $customer) {
            throw ValidationException::withMessages(['target_customer_id' => 'Target customer not found.']);
        }

        $staging = DB::table('migration_customers')
            ->where('legacy_user_id', $legacyUserId)
            ->orderByDesc('id')
            ->first();

        if ($staging === null) {
            throw ValidationException::withMessages([
                'legacy_user_id' => 'No migration staging row exists for this legacy user.',
            ]);
        }

        $existingMap = $this->maps->find(
            MigrationEntityMapRepository::TYPE_CUSTOMER,
            (string) $legacyUserId,
        );

        if ($existingMap && (int) $existingMap->target_id === $targetCustomerId) {
            $updatedFields = $this->applyFieldUpdates($customer, $fieldUpdates, $legacyUserId, $staging);

            return [
                'message' => "Legacy user {$legacyUserId} is already mapped to customer #{$targetCustomerId}."
                    .($updatedFields !== [] ? ' Customer fields updated.' : ''),
                'target_customer_id' => $targetCustomerId,
                'fields_updated' => $updatedFields,
            ];
        }

        if ($existingMap && (int) $existingMap->target_id !== $targetCustomerId) {
            throw ValidationException::withMessages([
                'target_customer_id' => 'This legacy user is already mapped to customer #'
                    .$existingMap->target_id.'. Remove or change that map before remapping.',
            ]);
        }

        $this->maps->storeOrUpdate(
            MigrationEntityMapRepository::TYPE_CUSTOMER,
            (string) $legacyUserId,
            Customer::class,
            $targetCustomerId,
            'dashboard_manual_map',
            'HIGH',
            $staging->legacy_customer_id ? (string) $staging->legacy_customer_id : null,
            (int) ($staging->migration_run_id ?? 0) ?: null,
            [
                'mapped_by_admin_id' => $adminId,
                'reason' => $reason,
                'previous_exception' => $staging->exception,
                'previous_status' => $staging->migration_status,
            ],
        );

        DB::table('migration_customers')
            ->where('legacy_user_id', $legacyUserId)
            ->where('migration_status', 'manual_review')
            ->update([
                'migration_status' => 'matched_existing',
                'mapped_customer_id' => $targetCustomerId,
                'updated_at' => now(),
            ]);

        $updatedFields = $this->applyFieldUpdates($customer, $fieldUpdates, $legacyUserId, $staging);

        $message = "Mapped legacy user {$legacyUserId} to customer #{$targetCustomerId} ({$customer->fresh()->full_name}). "
            .'Loan migration can now use this mapping.';
        if ($updatedFields !== []) {
            $message .= ' Updated: '.implode(', ', $updatedFields).'.';
        }

        return [
            'message' => $message,
            'target_customer_id' => $targetCustomerId,
            'fields_updated' => $updatedFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $fieldUpdates
     * @return list<string>
     */
    private function applyFieldUpdates(Customer $customer, array $fieldUpdates, int $legacyUserId, object $staging): array
    {
        $allowed = array_flip(MigrationCustomerMapFieldCatalog::allowedKeys());
        $payload = [];

        foreach ($fieldUpdates as $key => $value) {
            if (! isset($allowed[$key])) {
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            if (in_array($key, ['gross_salary', 'net_salary'], true)) {
                $payload[$key] = (float) str_replace(',', '', $value);
            } else {
                $payload[$key] = $value;
            }
        }

        $metadata = array_merge($customer->metadata ?? [], [
            'legacy_user_id' => $legacyUserId,
            'legacy_customer_id' => $staging->legacy_customer_id ?? null,
            'legacy_client_id' => $staging->legacy_client_id ?? null,
            'source_system' => 'finedge_legacy',
            'dashboard_manual_map_at' => now()->toIso8601String(),
        ]);

        $payload['metadata'] = $metadata;
        $customer->update($payload);

        return array_values(array_diff(array_keys($payload), ['metadata']));
    }
}
