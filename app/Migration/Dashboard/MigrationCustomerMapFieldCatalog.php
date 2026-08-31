<?php

namespace App\Migration\Dashboard;

use App\Models\Customer;

class MigrationCustomerMapFieldCatalog
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'first_name', 'label' => 'First name'],
            ['key' => 'last_name', 'label' => 'Last name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'national_id', 'label' => 'National ID (NRC)'],
            ['key' => 'employee_number', 'label' => 'Employee number'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'address_line1', 'label' => 'Address'],
            ['key' => 'city', 'label' => 'City'],
            ['key' => 'gross_salary', 'label' => 'Gross salary'],
            ['key' => 'net_salary', 'label' => 'Net salary'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return list<array<string, mixed>>
     */
    public static function comparisonRows(array $legacy, Customer $customer): array
    {
        $rows = [];

        foreach (self::definitions() as $definition) {
            $key = $definition['key'];
            $legacyValue = self::formatValue($key, self::legacyValue($legacy, $key));
            $currentValue = self::formatValue($key, $customer->{$key} ?? null);
            $legacyHasValue = self::hasValue($legacyValue);
            $currentHasValue = self::hasValue($currentValue);
            $differs = $legacyValue !== $currentValue;

            $rows[] = [
                'key' => $key,
                'label' => $definition['label'],
                'legacy' => $legacyValue,
                'current' => $currentValue,
                'differs' => $differs,
                'legacy_has_value' => $legacyHasValue,
                'current_has_value' => $currentHasValue,
                'suggest_legacy' => $legacyHasValue && (! $currentHasValue || $differs),
                'final' => $currentHasValue ? $currentValue : $legacyValue,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private static function legacyValue(array $legacy, string $key): mixed
    {
        return match ($key) {
            'first_name', 'last_name', 'email', 'phone', 'national_id', 'employee_number',
            'department', 'position', 'address_line1', 'city', 'gross_salary', 'net_salary' => $legacy[$key] ?? null,
            default => null,
        };
    }

    private static function formatValue(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($key, ['gross_salary', 'net_salary'], true) && is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return trim((string) $value);
    }

    private static function hasValue(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
