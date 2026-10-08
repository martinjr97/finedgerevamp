<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    public const EMPLOYMENT_ACTIVE = 'active';

    protected $fillable = [
        'employee_number',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'national_id',
        'nationality',
        'marital_status',
        'phone',
        'alternative_phone',
        'email',
        'personal_email',
        'residential_address',
        'postal_address',
        'profile_photo_path',
        'department',
        'employment_status',
        'department_id',
        'position_id',
        'reports_to_employee_id',
        'date_joined',
        'employment_start_date',
        'work_location',
        'branch_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
            'date_joined' => 'date',
            'employment_start_date' => 'date',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '))
        );
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->department_id && $employee->relationLoaded('hrDepartment') === false) {
                $dept = Department::query()->find($employee->department_id);
                if ($dept) {
                    $employee->department = $dept->name;
                }
            }
            if ($employee->branch_id) {
                $branch = Branch::query()->find($employee->branch_id);
                if ($branch) {
                    $employee->work_location = $branch->name;
                }
            }
            $employee->is_active = $employee->employment_status === self::EMPLOYMENT_ACTIVE;
        });
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function hrDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_employee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->orderByDesc('start_date');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(EmployeeContract::class)->where('status', EmployeeContract::STATUS_ACTIVE)->latestOfMany('start_date');
    }

    public function compensations(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class)->orderByDesc('effective_from');
    }

    public function currentCompensation(): HasOne
    {
        return $this->hasOne(EmployeeCompensation::class)->where('is_current', true)->latestOfMany('effective_from');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(EmployeeBankAccount::class)->where('is_primary', true)->where('is_active', true);
    }

    public function nextOfKin(): HasMany
    {
        return $this->hasMany(EmployeeNextOfKin::class);
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(EmployeeDependant::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function leaveTransactions(): HasMany
    {
        return $this->hasMany(EmployeeLeaveTransaction::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function scopeActive($query)
    {
        return $query->where('employment_status', self::EMPLOYMENT_ACTIVE);
    }

    public static function generateEmployeeNumber(): string
    {
        do {
            $number = 'EMP-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (self::query()->where('employee_number', $number)->exists());

        return $number;
    }
}
