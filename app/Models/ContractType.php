<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_permanent',
        'has_end_date',
        'default_duration_months',
        'leave_accrual_enabled',
        'leave_days_per_month',
        'probation_months',
        'renewable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_permanent' => 'boolean',
            'has_end_date' => 'boolean',
            'leave_accrual_enabled' => 'boolean',
            'renewable' => 'boolean',
            'is_active' => 'boolean',
            'leave_days_per_month' => 'decimal:2',
        ];
    }

    public function leaveRules(): HasMany
    {
        return $this->hasMany(ContractTypeLeaveRule::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }
}
