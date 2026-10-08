<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_paid',
        'requires_attachment',
        'accrual_based',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'accrual_based' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaveRules(): HasMany
    {
        return $this->hasMany(ContractTypeLeaveRule::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EmployeeLeaveTransaction::class);
    }
}
