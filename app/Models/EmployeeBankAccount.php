<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'financial_institution_id',
        'bank_name',
        'branch_name',
        'branch_code',
        'account_name',
        'account_number',
        'account_type',
        'currency',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function financialInstitution(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class);
    }

    public function maskedAccountNumber(): string
    {
        $number = (string) $this->account_number;
        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
