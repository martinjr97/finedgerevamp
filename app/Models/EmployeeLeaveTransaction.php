<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmployeeLeaveTransaction extends Model
{
    public const TYPE_ACCRUAL = 'accrual';

    public const TYPE_LEAVE_TAKEN = 'leave_taken';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_CARRY_FORWARD = 'carry_forward';

    public const TYPE_REVERSAL = 'reversal';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'transaction_date',
        'type',
        'days',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'days' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
