<?php

namespace App\Rules;

use App\Support\PhoneNumberFormatter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ZambianPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail(PhoneNumberFormatter::diagnose(is_scalar($value) ? (string) $value : ''));

            return;
        }

        if (! PhoneNumberFormatter::isValid($value)) {
            $fail(PhoneNumberFormatter::diagnose($value));
        }
    }
}
