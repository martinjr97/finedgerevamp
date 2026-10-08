<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTypeLeaveRule extends Model
{
    public const GENDER_ALL = 'all';

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    protected $fillable = [
        'contract_type_id',
        'leave_type_id',
        'days_per_month',
        'annual_cap',
        'carry_forward_allowed',
        'maximum_carry_forward',
        'requires_accrual',
        'applicable_gender',
    ];

    protected function casts(): array
    {
        return [
            'days_per_month' => 'decimal:2',
            'annual_cap' => 'decimal:2',
            'maximum_carry_forward' => 'decimal:2',
            'carry_forward_allowed' => 'boolean',
            'requires_accrual' => 'boolean',
        ];
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function appliesToEmployeeGender(?string $gender): bool
    {
        $scope = $this->applicable_gender ?? self::GENDER_ALL;
        if ($scope === self::GENDER_ALL) {
            return true;
        }

        if ($gender === null || trim($gender) === '') {
            return false;
        }

        return strtolower($gender) === $scope;
    }

    public function applicableGenderLabel(): string
    {
        return match ($this->applicable_gender ?? self::GENDER_ALL) {
            self::GENDER_MALE => 'Male employees',
            self::GENDER_FEMALE => 'Female employees',
            default => 'Male & female',
        };
    }
}
