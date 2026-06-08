<?php

namespace App\Support;

use App\Rules\ZambianPhoneNumber;
use Illuminate\Validation\Rule;

class ZambianPhoneRules
{
    public static function nullable(): array
    {
        return ['nullable', 'string', new ZambianPhoneNumber()];
    }

    public static function required(): array
    {
        return ['required', 'string', new ZambianPhoneNumber()];
    }

    /**
     * @return array<int, mixed>
     */
    public static function nullableUnique(string $column = 'phone', ?int $ignoreId = null, string $table = 'customers'): array
    {
        $rules = self::nullable();
        $unique = Rule::unique($table, $column);
        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }
        $rules[] = $unique;

        return $rules;
    }

    /**
     * Friendly attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'phone' => 'mobile number',
            'next_of_kin_phone' => 'next of kin mobile number',
            'disbursement_phone_number' => 'disbursement mobile number',
            'phone_number' => 'mobile money number',
            'wallet_number' => 'wallet mobile number',
        ];
    }

    /**
     * Extra messages for phone-related validation (merge into $request->validate()).
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.required' => 'Enter the customer mobile number in 260XXXXXXXXX format (e.g. '.PhoneNumberFormatter::PLACEHOLDER.').',
            'phone.unique' => 'This mobile number is already registered. Use a different number or find the existing customer.',
            'next_of_kin_phone.required' => 'Enter the next of kin mobile number in 260XXXXXXXXX format (e.g. '.PhoneNumberFormatter::PLACEHOLDER.').',
            'disbursement_phone_number.required' => 'Enter the mobile money number for disbursement in 260XXXXXXXXX format (e.g. '.PhoneNumberFormatter::PLACEHOLDER.').',
            'phone_number.required' => 'Enter the mobile money number in 260XXXXXXXXX format (e.g. '.PhoneNumberFormatter::PLACEHOLDER.').',
            'phone_number.required_if' => 'Enter a mobile money number in 260XXXXXXXXX format, or choose to use your profile number.',
            'wallet_number.required' => 'Enter the wallet mobile number in 260XXXXXXXXX format (e.g. '.PhoneNumberFormatter::PLACEHOLDER.').',
            'wallet_number.unique' => 'This wallet number is already registered.',
        ];
    }
}
