<?php

namespace App\Support;

class EmployeeTitles
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Mr',
            'Mrs',
            'Ms',
            'Miss',
            'Dr',
            'Prof',
            'Rev',
            'Eng',
            'Hon',
        ];
    }
}
