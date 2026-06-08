<?php

namespace App\Support;

class PhoneNumberFormatter
{
    public const PATTERN = '/^260(95|96|97|75|76|77)\d{7}$/';

    public const PLACEHOLDER = '260978232334';

    public const HTML_PATTERN = '260(95|96|97|75|76|77)[0-9]{7}';

    public const HELP_TEXT = 'Enter 12 digits starting with 260 (e.g. 260900000000).';

    public const EXAMPLE_CONVERSION = '0978232334 → 260978232334';

    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match(self::PATTERN, $value);
    }

    /**
     * User-facing hint explaining what is wrong with the entered value.
     */
    public static function diagnose(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'Enter a mobile number in 260XXXXXXXXX format, e.g. '.self::PLACEHOLDER.'.';
        }

        $raw = trim($value);

        if (str_contains($raw, '+')) {
            return 'Remove the + sign. Use 260 only — e.g. '.self::PLACEHOLDER.' (not +'.self::PLACEHOLDER.').';
        }

        if (str_contains($raw, ' ') || str_contains($raw, '-')) {
            return 'Remove spaces and hyphens. Enter 12 digits only, e.g. '.self::PLACEHOLDER.'.';
        }

        if (! preg_match('/^\d+$/', $raw)) {
            return 'Use digits only. Example: '.self::PLACEHOLDER.'.';
        }

        if (str_starts_with($raw, '0')) {
            return 'Do not start with 0. Change '.self::EXAMPLE_CONVERSION.' (drop the 0, add 260 at the start).';
        }

        if (! str_starts_with($raw, '260')) {
            return 'Number must start with 260. Example: '.self::PLACEHOLDER.'.';
        }

        if (strlen($raw) < 12) {
            return 'Number is too short ('.strlen($raw).' digits). Enter exactly 12 digits, e.g. '.self::PLACEHOLDER.'.';
        }

        if (strlen($raw) > 12) {
            return 'Number is too long ('.strlen($raw).' digits). Enter exactly 12 digits, e.g. '.self::PLACEHOLDER.'.';
        }

        if (! preg_match('/^260(95|96|97|75|76|77)/', $raw)) {
            return 'After 260, use a valid Zambian mobile prefix: 95, 96, 97, 75, 76, or 77 (e.g. '.self::PLACEHOLDER.').';
        }

        return 'Enter a valid Zambian mobile number, e.g. '.self::PLACEHOLDER.'.';
    }

    /**
     * Strip spaces and hyphens only (no reformatting).
     */
    public static function stripFormatting(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_replace([' ', '-'], '', $value);
    }

    /**
     * Digits only — use only after validation or for login lookup expansion.
     */
    public static function digitsOnly(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }

    /**
     * Expand local 0XXXXXXXXX to 260XXXXXXXXX for lookup only (does not validate).
     */
    public static function expandLocalForLookup(string $digits): string
    {
        if (preg_match('/^0(95|96|97|75|76|77)\d{7}$/', $digits)) {
            return '260'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Candidate values for login/lookup (does not modify stored data).
     *
     * @return list<string>
     */
    public static function lookupCandidates(string $input): array
    {
        $digits = self::digitsOnly($input) ?? '';
        if ($digits === '') {
            return [];
        }

        return array_values(array_unique(array_filter([
            $digits,
            self::expandLocalForLookup($digits),
        ])));
    }
}
