<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Anonymizes a customer account for deletion without removing the record.
 * Preserves referential integrity (loans, repayments, etc.) while making
 * the account unusable and PII unrecoverable.
 */
class AccountDeletionService
{
    public function deleteAccount(Customer $customer): void
    {
        $id = $customer->id;

        // Revoke all API tokens so the account cannot be used via API
        $customer->tokens()->delete();

        // Anonymize PII – use unique placeholders so DB unique constraints hold
        $customer->forceFill([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'registered_name' => null,
            'email' => 'deleted_' . $id . '@deleted.local',
            'phone' => null,
            'national_id' => null,
            'tpin' => null,
            'password' => bcrypt(Str::random(64)),
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'work_address_line1' => null,
            'work_address_line2' => null,
            'work_city' => null,
            'work_postal_code' => null,
            'work_country' => null,
            'next_of_kin_name' => null,
            'next_of_kin_phone' => null,
            'next_of_kin_relationship' => null,
            'next_of_kin_address_line1' => null,
            'next_of_kin_address_line2' => null,
            'next_of_kin_city' => null,
            'next_of_kin_country' => null,
            'security_question_id' => null,
            'security_answer' => null,
            'avatar_path' => null,
            'metadata' => null,
            'email_verified_at' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'status' => 'closed',
            'remember_token' => null,
        ]);

        $customer->save();

        // Soft delete so the record is excluded from normal queries but FKs remain valid
        $customer->delete();
    }
}
