<?php

namespace App\Rules;

use App\Support\NationalIdRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNationalIdNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $type = request()->input('national_id_type');

        if ($type === NationalIdRules::TYPE_NRC) {
            (new ZambianNrcNumber())->validate($attribute, $value, $fail);

            return;
        }

        if (! is_string($value) || strlen($value) > 50) {
            $fail('Enter a valid national ID number (maximum 50 characters).');
        }
    }
}
