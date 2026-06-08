<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ZambianNrcNumber implements ValidationRule
{
    public const PATTERN = '/^\d{6}\/\d{2}\/\d$/';

    public const PLACEHOLDER = '111111/11/1';

    public const HTML_PATTERN = '\\d{6}/\\d{2}/\\d';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail($this->message());
        }
    }

    public function message(): string
    {
        return 'Enter NRC in the correct format, e.g. '.self::PLACEHOLDER.'.';
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }
}
