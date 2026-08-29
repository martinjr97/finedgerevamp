<?php

namespace App\Migration\Phases\Support;

use App\Models\Customer;

class CustomerMatcher
{
    /**
     * @param  array<string, mixed>  $legacyUser
     * @param  array<string, mixed>|null  $legacyCustomer
     */
    public function matchExisting(array $legacyUser, ?array $legacyCustomer): array
    {
        $userId = (int) ($legacyUser['id'] ?? 0);

        $byMetadata = Customer::query()
            ->where('metadata->legacy_user_id', $userId)
            ->first();
        if ($byMetadata) {
            return ['customer' => $byMetadata, 'method' => 'explicit_migration_mapping', 'confidence' => 'HIGH', 'status' => 'MATCHED_EXISTING'];
        }

        $nrc = trim((string) ($legacyCustomer['nrc'] ?? $legacyUser['nrc'] ?? ''));
        if ($nrc !== '') {
            $byNrc = Customer::query()->where('national_id', $nrc)->get();
            if ($byNrc->count() === 1) {
                return ['customer' => $byNrc->first(), 'method' => 'national_id', 'confidence' => 'HIGH', 'status' => 'MATCHED_EXISTING'];
            }
            if ($byNrc->count() > 1) {
                return ['customer' => null, 'method' => 'national_id', 'confidence' => 'LOW', 'status' => 'POSSIBLE_DUPLICATE'];
            }
        }

        $email = trim((string) ($legacyUser['email'] ?? ''));
        if ($email !== '' && ! str_contains($email, '@migration.local')) {
            $byEmail = Customer::query()->where('email', $email)->first();
            if ($byEmail) {
                return ['customer' => $byEmail, 'method' => 'email', 'confidence' => 'MEDIUM', 'status' => 'POSSIBLE_DUPLICATE'];
            }
        }

        return ['customer' => null, 'method' => null, 'confidence' => 'LOW', 'status' => 'NEW'];
    }
}
