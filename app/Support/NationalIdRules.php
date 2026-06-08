<?php

namespace App\Support;

use App\Rules\ValidNationalIdNumber;
use Illuminate\Validation\Rule;

class NationalIdRules
{
    public const TYPE_NRC = 'nrc';

    public const TYPE_PASSPORT = 'passport';

    public const TYPE_DRIVERS_LICENCE = 'drivers_licence';

  /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_NRC => 'NRC',
            self::TYPE_PASSPORT => 'Passport',
            self::TYPE_DRIVERS_LICENCE => 'Driver’s Licence',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function type(): array
    {
        return [
            'required',
            Rule::in(array_keys(self::typeLabels())),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function nationalId(?string $uniqueTable = 'customers', ?int $ignoreId = null): array
    {
        $rules = [
            'required',
            'string',
            'max:50',
            new ValidNationalIdNumber(),
        ];

        if ($uniqueTable !== null) {
            $unique = Rule::unique($uniqueTable, 'national_id');
            if ($ignoreId !== null) {
                $unique->ignore($ignoreId);
            }
            $rules[] = $unique;
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    public static function tpin(?string $uniqueTable = 'customers', ?int $ignoreId = null): array
    {
        $rules = ['nullable', 'string', 'max:50'];

        if ($uniqueTable !== null) {
            $unique = Rule::unique($uniqueTable, 'tpin');
            if ($ignoreId !== null) {
                $unique->ignore($ignoreId);
            }
            $rules[] = $unique;
        }

        return $rules;
    }

    /**
     * Merge identity rules into a validation rules array.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function merge(array $rules, ?int $ignoreCustomerId = null): array
    {
        return array_merge($rules, [
            'national_id_type' => self::type(),
            'national_id' => self::nationalId('customers', $ignoreCustomerId),
            'tpin' => self::tpin('customers', $ignoreCustomerId),
        ]);
    }

    /**
     * Identity rules for public registration requests (no uniqueness checks).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function mergeRegistration(array $rules): array
    {
        unset($rules['national_id'], $rules['tpin']);

        return array_merge($rules, [
            'national_id_type' => self::type(),
            'national_id' => self::nationalId(null),
            'tpin' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'national_id_type.required' => 'Select the national ID type (NRC, Passport, or Driver’s Licence).',
            'national_id_type.in' => 'National ID type must be NRC, Passport, or Driver’s Licence.',
            'national_id.required' => 'National ID is required.',
            'national_id.unique' => 'This national ID is already registered to another customer.',
            'tpin.unique' => 'This TPIN is already registered to another customer.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'national_id_type' => 'national ID type',
            'national_id' => 'national ID',
            'tpin' => 'TPIN',
        ];
    }
}
