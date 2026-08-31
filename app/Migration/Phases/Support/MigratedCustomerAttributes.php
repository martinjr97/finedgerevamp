<?php

namespace App\Migration\Phases\Support;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class MigratedCustomerAttributes
{
    /**
     * @param  array<string, mixed>  $legacyUser
     * @param  array<string, mixed>  $legacyCustomer
     * @return array{created_at: Carbon, updated_at: Carbon}|null
     */
    public static function resolveRegistrationTimestamps(array $legacyUser, array $legacyCustomer): ?array
    {
        $createdRaw = $legacyCustomer['created_at'] ?? $legacyUser['created_at'] ?? null;
        if ($createdRaw === null || $createdRaw === '') {
            return null;
        }

        try {
            $createdAt = Carbon::parse((string) $createdRaw);
        } catch (\Throwable) {
            return null;
        }

        $updatedRaw = $legacyCustomer['updated_at'] ?? $legacyUser['updated_at'] ?? $createdRaw;

        try {
            $updatedAt = Carbon::parse((string) $updatedRaw);
        } catch (\Throwable) {
            $updatedAt = $createdAt->copy();
        }

        if ($updatedAt->lt($createdAt)) {
            $updatedAt = $createdAt->copy();
        }

        return [
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     * @param  array<string, mixed>  $legacyCustomer
     */
    public static function legacyRegisteredAtIso(array $legacyUser, array $legacyCustomer): ?string
    {
        $timestamps = self::resolveRegistrationTimestamps($legacyUser, $legacyCustomer);
        if ($timestamps === null) {
            return null;
        }

        return $timestamps['created_at']->toIso8601String();
    }

    /**
     * Apply legacy registration timestamps so dashboard "new customers" uses the original signup date.
     *
     * @param  array<string, mixed>  $legacyUser
     * @param  array<string, mixed>  $legacyCustomer
     */
    public static function applyRegistrationTimestamps(
        Customer $customer,
        array $legacyUser,
        array $legacyCustomer,
        bool $force = false,
    ): bool {
        $timestamps = self::resolveRegistrationTimestamps($legacyUser, $legacyCustomer);
        if ($timestamps === null) {
            return false;
        }

        if (! $force && self::hasLegacyRegistrationDate($customer)) {
            return false;
        }

        $customer->forceFill([
            'created_at' => $timestamps['created_at'],
            'updated_at' => $timestamps['updated_at'],
            'metadata' => array_merge($customer->metadata ?? [], [
                'legacy_registered_at' => $timestamps['created_at']->toIso8601String(),
                'source_system' => ($customer->metadata ?? [])['source_system'] ?? 'finedge_legacy',
            ]),
        ])->saveQuietly();

        return true;
    }

    public static function hasLegacyRegistrationDate(Customer $customer): bool
    {
        $metadata = $customer->metadata ?? [];

        return ! empty($metadata['legacy_registered_at']);
    }

    /**
     * Legacy users.password is a bcrypt hash of the customer's 4-digit PIN.
     *
     * @param  array<string, mixed>  $legacyUser
     */
    public static function resolveLegacyPasswordHash(array $legacyUser): ?string
    {
        $hash = trim((string) ($legacyUser['password'] ?? ''));
        if ($hash === '' || ! Hash::isHashed($hash)) {
            return null;
        }

        return $hash;
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    public static function applyLegacyPassword(Customer $customer, array $legacyUser, bool $force = false): bool
    {
        $hash = self::resolveLegacyPasswordHash($legacyUser);
        if ($hash === null) {
            return false;
        }

        $metadata = $customer->metadata ?? [];
        if (! $force && ! empty($metadata['legacy_password_migrated'])) {
            return false;
        }

        $customer->forceFill([
            'password' => $hash,
            'must_change_pin' => false,
            'metadata' => array_merge($metadata, [
                'legacy_password_migrated' => true,
                'source_system' => $metadata['source_system'] ?? 'finedge_legacy',
            ]),
        ])->saveQuietly();

        return true;
    }
}
